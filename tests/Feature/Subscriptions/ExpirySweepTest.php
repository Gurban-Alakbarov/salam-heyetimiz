<?php

use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use App\Domain\Subscriptions\Services\ExpirySweep;
use Illuminate\Support\Facades\Event;

/*
| Tech Spec §13.4–13.5 daily sweeps: expire past-due subscriptions; fire reminders once per threshold.
*/

it('expires active subscriptions whose term has ended, and leaves others alone', function () {
    Event::fake([SubscriptionExpired::class]);

    $owner = makeUser('+994500000070');
    $deviceA = makeActiveDevice('SER-E1', '+994700000070');
    $deviceB = makeActiveDevice('SER-E2', '+994700000071');
    $expired = makeSubscription(makeDeviceUser($owner, $deviceA), ['ends_at' => now()->subDay()]);
    $valid = makeSubscription(makeDeviceUser($owner, $deviceB), ['ends_at' => now()->addDays(30)]);

    $count = app(ExpirySweep::class)->expireBatch();

    expect($count)->toBe(1)
        ->and($expired->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and($valid->fresh()->status)->toBe(SubscriptionStatus::Active);

    Event::assertDispatched(SubscriptionExpired::class, fn (SubscriptionExpired $e) => $e->subscription->is($expired));
});

it('fires a reminder once per threshold (single-fire progression)', function () {
    Event::fake([SubscriptionExpiringSoon::class]);

    $owner = makeUser('+994500000071');
    $device = makeActiveDevice('SER-E3', '+994700000072');
    $sub = makeSubscription(makeDeviceUser($owner, $device), ['ends_at' => now()->addDays(5)]); // → D7

    $sent = app(ExpirySweep::class)->sendReminders();

    expect($sent)->toBe(1)
        ->and($sub->fresh()->last_reminder_kind)->toBe(ReminderKind::D7)
        ->and($sub->fresh()->last_reminder_sent_at)->not->toBeNull();

    // A second sweep at the same threshold must not re-fire.
    $again = app(ExpirySweep::class)->sendReminders();
    expect($again)->toBe(0);

    Event::assertDispatchedTimes(SubscriptionExpiringSoon::class, 1);
});

it('does not remind subscriptions more than 30 days out', function () {
    Event::fake([SubscriptionExpiringSoon::class]);

    $owner = makeUser('+994500000072');
    $device = makeActiveDevice('SER-E4', '+994700000073');
    makeSubscription(makeDeviceUser($owner, $device), ['ends_at' => now()->addDays(45)]);

    expect(app(ExpirySweep::class)->sendReminders())->toBe(0);
    Event::assertNotDispatched(SubscriptionExpiringSoon::class);
});
