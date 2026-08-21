<?php

use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Users\Models\User;

/*
| Admin resident management: grant an entitlement WITHOUT a payment (subscriptions.manage), and remove a
| resident from the SYSTEM (residents.delete) — not just from one device's roster.
*/

function canOpen(User $user, int $deviceId): bool
{
    return app(SubscriptionStatusQuery::class)->for((int) $user->id, $deviceId)->canOpen;
}

// ---------------------------------------------------------------- grant subscription (no payment)

it('grants a subscription without a payment, making the resident open-permitted', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600001');
    $device = makeOwnedDevice($owner, 'RES-GRANT-1', '+994700600002');
    $member = makeUser('+994700600003');
    makeDeviceUser($member, $device, 'user');

    expect(canOpen($member, (int) $device->id))->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/users/{$member->id}/subscription", ['term_days' => 30])
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active');

    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->firstOrFail();
    $subscription = Subscription::query()->where('device_user_id', $du->id)->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->price_minor)->toBe(0)              // comp — no money changed hands
        ->and($subscription->term_days)->toBe(30)
        ->and($subscription->ends_at->isFuture())->toBeTrue()
        ->and(canOpen($member, (int) $device->id))->toBeTrue()  // the whole point: they can now open
        ->and(Order::query()->count())->toBe(0)                 // and Payments was never involved
        ->and($subscription->periods()->count())->toBe(0);      // a comp grant records no billing period
});

it('extends a live subscription from its current end instead of restarting the term', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600011');
    $device = makeOwnedDevice($owner, 'RES-GRANT-2', '+994700600012');
    $member = makeUser('+994700600013');
    $du = makeDeviceUser($member, $device, 'user');
    $existing = makeSubscription($du, ['ends_at' => now()->addDays(10), 'status' => SubscriptionStatus::Active]);
    $endBefore = $existing->refresh()->ends_at;

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/users/{$member->id}/subscription", ['term_days' => 30])
        ->assertOk();

    // 10 remaining days are NOT lost — the new term starts where the live one ended.
    expect($existing->refresh()->ends_at->toDateString())->toBe($endBefore->copy()->addDays(30)->toDateString());
});

it('refuses to grant a subscription to someone who is not on the roster', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600021');
    $device = makeOwnedDevice($owner, 'RES-GRANT-3', '+994700600022');
    $stranger = makeUser('+994700600023');

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/users/{$stranger->id}/subscription", [])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'roster_member_not_found');
});

it('gates the grant behind subscriptions.manage', function () {
    $technical = makeAdminRole('technical'); // devices only — no subscriptions.manage
    $owner = makeUser('+994700600031');
    $device = makeOwnedDevice($owner, 'RES-GRANT-4', '+994700600032');
    $member = makeUser('+994700600033');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($technical, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/users/{$member->id}/subscription", [])
        ->assertForbidden();

    expect(Subscription::query()->count())->toBe(0);
});

// ---------------------------------------------------------------- delete resident (whole account)

it('deletes a resident from the system: revokes memberships, cancels entitlements, de-whitelists, soft-deletes', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600041');
    $device = makeOwnedDevice($owner, 'RES-DEL-1', '+994700600042');
    $member = makeUser('+994700600043');
    $du = makeDeviceUser($member, $device, 'user');
    makeSubscription($du, ['status' => SubscriptionStatus::Active]);

    expect(canOpen($member, (int) $device->id))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->deleteJson("/admin/v1/residents/{$member->id}")
        ->assertNoContent();

    $du->refresh();

    expect($du->status->value)->toBe('revoked')
        ->and(Subscription::query()->where('device_user_id', $du->id)->first()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(User::query()->find($member->id))->toBeNull()                       // soft-deleted → gone from default scope
        ->and(User::withTrashed()->find($member->id)->deleted_at)->not->toBeNull()
        ->and(canOpen($member, (int) $device->id))->toBeFalse()                   // access is gone
        ->and(WhitelistChange::query()->where('device_id', $device->id)->where('action', 'remove')->where('phone', '+994700600043')->count())->toBe(1);
});

it('refuses to delete a resident who still owns a device', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600051');
    $device = makeOwnedDevice($owner, 'RES-DEL-2', '+994700600052');

    $this->actingAs($admin, 'admin')
        ->deleteJson("/admin/v1/residents/{$owner->id}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'resident_owns_device');

    expect(User::query()->find($owner->id))->not->toBeNull()
        ->and($device->refresh()->owner_user_id)->toBe((int) $owner->id);
});

it('still renders the device detail after a member is deleted from the system (no null user)', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700600071');
    $device = makeOwnedDevice($owner, 'RES-DEL-4', '+994700600072');
    $member = makeUser('+994700600073');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($admin, 'admin')->deleteJson("/admin/v1/residents/{$member->id}")->assertNoContent();

    // REGRESSION: the roster row survives the soft delete. Without withTrashed on the eager load the
    // relation resolved to null → `users[].user: null` → the admin SPA crashed to a blank page.
    $res = $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}")->assertOk();
    $row = collect($res->json('users'))->firstWhere('user.id', (int) $member->id);

    expect($row)->not->toBeNull()
        ->and($row['user'])->not->toBeNull()          // never null — the page must render
        ->and($row['user']['deleted'])->toBeTrue()    // and be flagged so the UI can mark it
        ->and($row['status'])->toBe('revoked');
});

it('gates resident deletion behind residents.delete', function () {
    $support = makeAdminRole('support'); // read-only
    $owner = makeUser('+994700600061');
    $device = makeOwnedDevice($owner, 'RES-DEL-3', '+994700600062');
    $member = makeUser('+994700600063');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($support, 'admin')
        ->deleteJson("/admin/v1/residents/{$member->id}")
        ->assertForbidden();

    expect(User::query()->find($member->id))->not->toBeNull();
});
