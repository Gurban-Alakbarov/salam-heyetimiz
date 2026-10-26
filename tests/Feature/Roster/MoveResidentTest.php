<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\Enums\VisitorAccessType;
use Illuminate\Testing\TestResponse;

/*
| Y5 — an admin moves a resident to another gate (e.g. another entrance of the same complex) and the resident does
| NOT pay again: the remaining paid time moves with them. The old gate's access ends (whitelist, guest links), the
| new gate opens at once. Owners and family members do not move; refusals change nothing.
*/

beforeEach(function () {
    $this->super = makeSuperAdmin();
    $this->cx = makeComplex('CX-Y5', 'Kompleks Y5');
    $this->gateA = y5Gate($this->cx->id, 'SER-Y5-A', '+994700550101');
    $this->gateB = y5Gate($this->cx->id, 'SER-Y5-B', '+994700550102');

    $this->resident = makeUser('+994505550101');
    app(ComplexMembershipService::class)->join($this->cx->id, $this->resident);
    $this->rowA = makeDeviceUser($this->resident, $this->gateA, 'user');
    $this->endsAt = now()->addDays(20)->startOfSecond();
    $this->subA = makeSubscription($this->rowA, ['term_days' => 30, 'ends_at' => $this->endsAt]);
});

function y5Gate(int $complexId, string $serial, string $sim): Device
{
    $d = makeActiveDevice($serial, $sim);
    $d->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complexId, 'owner_user_id' => null])->save();

    return $d->refresh();
}

function y5Move(int $from, int $userId, int $to): TestResponse
{
    return test()->postJson("/admin/v1/devices/{$from}/users/{$userId}/move", ['target_device_id' => $to]);
}

function y5CanOpen(User $user, Device $device): bool
{
    return app(SubscriptionStatusQuery::class)->for((int) $user->id, (int) $device->id)->canOpen;
}

it('moves a resident with the remaining paid time: the new gate opens at once, nothing to pay', function () {
    [$link] = mintVisitorLink($this->gateA, $this->resident, VisitorAccessType::TimeLimited, 120, 'Kuryer');
    expect(y5CanOpen($this->resident, $this->gateA))->toBeTrue();

    $this->actingAs($this->super, 'admin');
    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertOk()
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('subscription.status', 'active');

    $rowB = DeviceUser::query()->where('device_id', $this->gateB->id)->where('user_id', $this->resident->id)->firstOrFail();
    $subB = Subscription::query()->where('device_user_id', $rowB->id)->firstOrFail();

    expect($this->rowA->fresh()->status)->toBe(DeviceUserStatus::Revoked)
        ->and($this->subA->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($this->subA->fresh()->cancellation_reason)->toBe('moved_to_device')
        ->and($subB->status)->toBe(SubscriptionStatus::Active)
        ->and($subB->ends_at->equalTo($this->endsAt))->toBeTrue()
        ->and($subB->tier->value)->toBe('main')
        ->and((int) $subB->price_minor)->toBe(1200)
        ->and(y5CanOpen($this->resident, $this->gateB))->toBeTrue()
        ->and(y5CanOpen($this->resident, $this->gateA))->toBeFalse()
        ->and($link->fresh()->revoked_at)->not->toBeNull(); // K7: the old gate's guest links go too

    $audit = AuditLog::query()->where('action', 'resident.moved')->firstOrFail();
    expect($audit->payload['subscription_moved'])->toBeTrue()
        ->and($audit->payload['to_subscription_id'])->toBe($subB->id);
});

it('moving back reuses the earlier row and its subscription record', function () {
    $this->actingAs($this->super, 'admin');
    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertOk();
    y5Move($this->gateB->id, $this->resident->id, $this->gateA->id)->assertOk();

    $sub = $this->subA->fresh();
    expect($this->rowA->fresh()->status)->toBe(DeviceUserStatus::Active)
        ->and($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->ends_at->equalTo($this->endsAt))->toBeTrue()
        ->and(Subscription::query()->whereIn('device_user_id', DeviceUser::query()->where('user_id', $this->resident->id)->pluck('id'))->count())->toBe(2)
        ->and(y5CanOpen($this->resident, $this->gateA))->toBeTrue();
});

it('without a live subscription only the access moves', function () {
    $this->subA->forceFill(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subDay()])->save();

    $this->actingAs($this->super, 'admin');
    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertOk()->assertJsonPath('subscription', null);

    expect($this->subA->fresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(DeviceUser::query()->where('device_id', $this->gateB->id)->where('user_id', $this->resident->id)->value('status'))->toBe(DeviceUserStatus::Active)
        ->and(y5CanOpen($this->resident, $this->gateB))->toBeFalse();
});

it('refuses what must not move, changing nothing', function () {
    $this->actingAs($this->super, 'admin');

    y5Move($this->gateA->id, $this->resident->id, $this->gateA->id)->assertStatus(422)->assertJsonPath('error.code', 'move_same_device');

    $disabled = y5Gate($this->cx->id, 'SER-Y5-C', '+994700550103');
    $disabled->forceFill(['status' => 'disabled'])->save();
    y5Move($this->gateA->id, $this->resident->id, $disabled->id)->assertStatus(422)->assertJsonPath('error.code', 'move_target_inactive');

    $foreign = y5Gate(makeComplex('CX-Y5-O', 'Başqa')->id, 'SER-Y5-D', '+994700550104');
    y5Move($this->gateA->id, $this->resident->id, $foreign->id)->assertStatus(422)->assertJsonPath('error.code', 'move_not_complex_member');

    makeSubscription(makeDeviceUser($this->resident, $this->gateB, 'user'));
    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertStatus(409)->assertJsonPath('error.code', 'move_target_subscribed');

    $stranger = makeUser('+994505550199');
    y5Move($this->gateA->id, $stranger->id, $this->gateB->id)->assertNotFound();

    expect($this->rowA->fresh()->status)->toBe(DeviceUserStatus::Active)
        ->and($this->subA->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('owners and family members do not move', function () {
    $owner = makeUser('+994505550110');
    $private = makeOwnedDevice($owner, 'SER-Y5-P1', '+994700550110');
    $privateB = makeOwnedDevice($owner, 'SER-Y5-P2', '+994700550111');

    $this->actingAs($this->super, 'admin');
    y5Move($private->id, $owner->id, $privateB->id)->assertStatus(422)->assertJsonPath('error.code', 'move_not_movable');

    $member = makeUser('+994505550111');
    $link = FamilyLink::query()->create(['head_user_id' => $this->resident->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    DeviceUser::query()->create(['device_id' => $this->gateA->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active', 'family_link_id' => $link->id, 'added_by_user_id' => $this->resident->id]);
    y5Move($this->gateA->id, $member->id, $this->gateB->id)->assertStatus(422)->assertJsonPath('error.code', 'move_not_movable');
});

it('works between private gates of the same owner too', function () {
    $owner = makeUser('+994505550120');
    $p1 = makeOwnedDevice($owner, 'SER-Y5-P3', '+994700550120');
    $p2 = makeOwnedDevice($owner, 'SER-Y5-P4', '+994700550121');
    $member = makeUser('+994505550121');
    $sub = makeSubscription(makeDeviceUser($member, $p1, 'user'), ['ends_at' => now()->addDays(9)]);

    $this->actingAs($this->super, 'admin');
    y5Move($p1->id, $member->id, $p2->id)->assertOk()->assertJsonPath('subscription.status', 'active');

    expect($sub->fresh()->cancellation_reason)->toBe('moved_to_device')
        ->and(y5CanOpen($member, $p2))->toBeTrue();
});

it('needs residents.update and both gates in the complex_manager\'s scope', function () {
    $this->actingAs(makeAdminRole('support'), 'admin');
    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertForbidden();

    $foreign = y5Gate(makeComplex('CX-Y5-X', 'Kənar')->id, 'SER-Y5-E', '+994700550105');
    $this->actingAs(makeAdminRole('complex_manager', $this->cx->id), 'admin');
    y5Move($this->gateA->id, $this->resident->id, $foreign->id)->assertNotFound();

    y5Move($this->gateA->id, $this->resident->id, $this->gateB->id)->assertOk();
});
