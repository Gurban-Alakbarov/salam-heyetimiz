<?php

use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;

/*
| Admin barrier/resident assignment (Roster): bind an owner, add/remove residents, and keep the whitelist
| outbox in sync. End state = the device-detail "users" roster is populated and the whitelist is enqueued.
*/

function whitelistAdds(int $deviceId, string $phone): int
{
    return WhitelistChange::query()->where('device_id', $deviceId)->where('action', 'add')->where('phone', $phone)->count();
}

function whitelistRemoves(int $deviceId, string $phone): int
{
    return WhitelistChange::query()->where('device_id', $deviceId)->where('action', 'remove')->where('phone', $phone)->count();
}

it('assigns an owner to an unassigned device, provisioning the resident by phone', function () {
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('ROS-ASG-1', '+994700500001');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/assign", [
        'owner_phone' => '+994505384489',
        'location_label' => 'Blok A',
    ])->assertOk()->assertJsonPath('status', 'active');

    $owner = User::query()->where('phone', '+994505384489')->firstOrFail(); // resident created
    $device->refresh();

    expect((int) $device->owner_user_id)->toBe((int) $owner->id)
        ->and($device->status)->toBe(DeviceStatus::Active)
        ->and(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->where('role', 'owner')->where('status', 'active')->exists())->toBeTrue()
        ->and(whitelistAdds($device->id, '+994505384489'))->toBe(1); // owner enqueued to whitelist
});

it('adds a resident to the roster and enqueues a whitelist add', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700500010');
    $device = makeOwnedDevice($owner, 'ROS-ADD-1', '+994700500011');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", [
        'phone' => '+994701500012',
    ])->assertStatus(201)
        ->assertJsonPath('role', 'user')
        ->assertJsonPath('status', 'active');

    $member = User::query()->where('phone', '+994701500012')->firstOrFail();

    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('role', 'user')->where('status', 'active')->exists())->toBeTrue()
        ->and(whitelistAdds($device->id, '+994701500012'))->toBe(1);
});

it('removes a resident from the roster and enqueues a whitelist remove', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700500020');
    $device = makeOwnedDevice($owner, 'ROS-RM-1', '+994700500021');
    $member = makeUser('+994701500022');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs($admin, 'admin')
        ->deleteJson("/admin/v1/devices/{$device->id}/users/{$member->id}")
        ->assertStatus(204);

    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('status', 'revoked')->exists())->toBeTrue()
        ->and(whitelistRemoves($device->id, '+994701500022'))->toBe(1);
});

it('refuses to add a resident to an unassigned device (409)', function () {
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('ROS-UNA-1', '+994700500030');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", [
        'phone' => '+994701500031',
    ])->assertStatus(409)->assertJsonPath('error.code', 'device_not_assigned');

    expect(User::query()->where('phone', '+994701500031')->exists())->toBeFalse();
});

it('refuses to remove the owner via roster removal (409)', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700500040');
    $device = makeOwnedDevice($owner, 'ROS-OWN-1', '+994700500041');

    $this->actingAs($admin, 'admin')
        ->deleteJson("/admin/v1/devices/{$device->id}/users/{$owner->id}")
        ->assertStatus(409)->assertJsonPath('error.code', 'cannot_remove_owner');

    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->where('status', 'active')->exists())->toBeTrue();
});

it('is idempotent — adding the same resident twice keeps one active roster row', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700500050');
    $device = makeOwnedDevice($owner, 'ROS-IDEM-1', '+994700500051');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994701500052'])->assertStatus(201);
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994701500052'])->assertStatus(201);

    $member = User::query()->where('phone', '+994701500052')->firstOrFail();
    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('status', 'active')->count())->toBe(1);
});

it('populates the device-detail users roster after assignment + add', function () {
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('ROS-DET-1', '+994700500060');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/assign", ['owner_phone' => '+994700500061'])->assertOk();
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994701500062'])->assertStatus(201);

    $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}")
        ->assertOk()
        ->assertJsonCount(2, 'users')
        ->assertJsonPath('users.0.role', 'owner');
});

it('re-adds a previously removed resident (whitelist re-enqueued)', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994700500070');
    $device = makeOwnedDevice($owner, 'ROS-READD-1', '+994700500071');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994701500072'])->assertStatus(201);
    $member = User::query()->where('phone', '+994701500072')->firstOrFail();
    $this->actingAs($admin, 'admin')->deleteJson("/admin/v1/devices/{$device->id}/users/{$member->id}")->assertStatus(204);
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994701500072'])->assertStatus(201);

    expect(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $member->id)->where('status', 'active')->count())->toBe(1)
        ->and(whitelistAdds($device->id, '+994701500072'))->toBe(2); // initial add + re-add
});

it('requires an admin token', function () {
    $device = makeUnassignedDevice('ROS-AUTH-1', '+994700500080');

    $this->postJson("/admin/v1/devices/{$device->id}/assign", ['owner_phone' => '+994700500081'])->assertStatus(401);
    $this->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994700500081'])->assertStatus(401);
});
