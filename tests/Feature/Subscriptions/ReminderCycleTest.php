<?php

use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use App\Domain\Subscriptions\Services\ExpirySweep;
use App\Domain\Subscriptions\Services\SubscriptionService;
use Illuminate\Support\Facades\Event;

/*
| Y1 — every period gets its own expiry reminders. A renewal (paid), an admin grant extension and an
| activation start a new end date, so the reminder progression is reset: D-7 and D-1 fire again before
| the NEW end. (Before, the first period's "D-1 sent" mark silenced every later period.)
*/

beforeEach(function () {
    Event::fake([SubscriptionExpiringSoon::class]);
    $this->owner = makeUser('+994500660001');
    $this->device = makeActiveDevice('SER-Y1-1', '+994700660001');
    $this->row = makeDeviceUser($this->owner, $this->device);
});

/** One daily reminder sweep; returns how many reminders it sent. */
function y1Sweep(): int
{
    return app(ExpirySweep::class)->sendReminders();
}

it('a paid renewal starts a new reminder cycle: D-7 and D-1 fire again before the new end', function () {
    $sub = makeSubscription($this->row, ['term_days' => 30, 'ends_at' => now()->addHours(12)]); // → D-1
    expect(y1Sweep())->toBe(1)->and($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D1);

    app(SubscriptionService::class)->renew($sub->fresh(), makePaidOrder($this->owner, 1200, 'KB-Y1-1'), 1200);
    $sub->refresh();
    expect($sub->last_reminder_kind)->toBeNull()->and($sub->last_reminder_sent_at)->toBeNull();
    $newEnd = $sub->ends_at;

    $this->travelTo($newEnd->copy()->subDays(5));
    expect(y1Sweep())->toBe(1)->and($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D7);

    $this->travelTo($newEnd->copy()->subHours(10));
    expect(y1Sweep())->toBe(1)->and($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D1);

    Event::assertDispatchedTimes(SubscriptionExpiringSoon::class, 3);
});

it('a renewal within the same period does not double-send (D-1 then renew then same day → nothing yet)', function () {
    $sub = makeSubscription($this->row, ['term_days' => 30, 'ends_at' => now()->addHours(12)]);
    y1Sweep();
    app(SubscriptionService::class)->renew($sub->fresh(), makePaidOrder($this->owner, 1200, 'KB-Y1-2'), 1200);

    // the new end is ~30 days away — outside every threshold today
    expect(y1Sweep())->toBe(0);
    Event::assertDispatchedTimes(SubscriptionExpiringSoon::class, 1);
});

it('an admin grant extension also starts a new reminder cycle', function () {
    $member = makeUser('+994500660002');
    $memberRow = makeDeviceUser($member, $this->device, DeviceUserRole::User->value);
    $sub = makeSubscription($memberRow, ['tier' => SubscriptionTier::Additional, 'term_days' => 30, 'ends_at' => now()->addHours(12)]);
    y1Sweep();
    expect($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D1);

    app(SubscriptionService::class)->grantManual($memberRow, SubscriptionTier::Additional, 30);
    $sub->refresh();
    expect($sub->last_reminder_kind)->toBeNull();

    $this->travelTo($sub->ends_at->copy()->subDays(5));
    expect(y1Sweep())->toBe(1)->and($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D7);
});
