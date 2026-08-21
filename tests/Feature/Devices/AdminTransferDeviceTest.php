<?php

use App\Domain\Devices\Events\DeviceTransferred;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Event;

/*
| POST /admin/v1/devices/{id}/transfer — adminTransferDevice (R-DOM-12, super_admin).
*/

it('transfers ownership and keeps existing members by default', function () {
    Event::fake([DeviceTransferred::class]);
    $admin = makeSuperAdmin();
    $oldOwner = makeUser('+994506500001');
    $member = makeUser('+994506500002');
    $device = makeOwnedDevice($oldOwner, 'TR-1', '+994701500001');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/transfer", [
        'new_owner_phone' => '+994506500003',
        'reason' => 'sold on',
    ])->assertOk();

    $newOwner = User::query()->where('phone', '+994506500003')->firstOrFail();
    $device->refresh();
    expect((int) $device->owner_user_id)->toBe($newOwner->id);

    // New owner active+owner; old owner revoked; member kept active.
    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $newOwner->id)->where('status', 'active')->where('role', 'owner')->exists())->toBeTrue()
        ->and(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $oldOwner->id)->where('status', 'active')->exists())->toBeFalse()
        ->and(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('status', 'active')->exists())->toBeTrue();

    Event::assertDispatched(DeviceTransferred::class);
});

it('revokes existing members when keep_existing_users is false', function () {
    $admin = makeSuperAdmin();
    $oldOwner = makeUser('+994506500010');
    $member = makeUser('+994506500011');
    $device = makeOwnedDevice($oldOwner, 'TR-2', '+994701500002');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/transfer", [
        'new_owner_phone' => '+994506500012',
        'reason' => 'fresh start',
        'keep_existing_users' => false,
    ])->assertOk();

    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('status', 'active')->exists())->toBeFalse();
});

it('forbids a technical admin from transferring (super_admin only)', function () {
    $tech = makeTechnicalAdmin();
    $device = makeOwnedDevice(makeUser('+994506500020'), 'TR-3', '+994701500003');

    $this->actingAs($tech, 'admin')->postJson("/admin/v1/devices/{$device->id}/transfer", [
        'new_owner_phone' => '+994506500021',
        'reason' => 'nope',
    ])->assertStatus(403);
});
