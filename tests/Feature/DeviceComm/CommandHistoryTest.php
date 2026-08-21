<?php

use App\Domain\Roster\Models\DeviceUser;

/*
| Command history reads: listDeviceCommands (caller-scoped), getCommand, adminDeviceCommands (all).
*/

it('lists the caller’s own commands for a device', function () {
    $owner = makeUser('+994509200001');
    $other = makeUser('+994509200002');
    $device = makeOpenableDevice($owner, 'SER-H1', '+994700092001');
    $ownerDu = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $otherDu = makeDeviceUser($other, $device, 'user');

    makeOpenCommand($device, $owner, $ownerDu, ['state' => 'dispatched']);
    makeOpenCommand($device, $owner, $ownerDu, ['state' => 'failed', 'failure_reason' => 'device_offline']);
    makeOpenCommand($device, $other, $otherDu, ['state' => 'dispatched']); // not the caller's

    $response = $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}/commands");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'device_id', 'state', 'driver', 'requested_at', 'attempts']], 'page']);
});

it('filters command history by state', function () {
    $owner = makeUser('+994509200003');
    $device = makeOpenableDevice($owner, 'SER-H2', '+994700092002');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    makeOpenCommand($device, $owner, $du, ['state' => 'dispatched']);
    makeOpenCommand($device, $owner, $du, ['state' => 'failed']);

    $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}/commands?state=failed")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.state', 'failed');
});

it('returns a single command to its owner, 404 to others', function () {
    $owner = makeUser('+994509200004');
    $stranger = makeUser('+994509200005');
    $device = makeOpenableDevice($owner, 'SER-H3', '+994700092003');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $command = makeOpenCommand($device, $owner, $du, ['state' => 'dispatched']);

    $this->actingAs($owner, 'user')->getJson("/v1/commands/{$command->id}")
        ->assertOk()
        ->assertJsonPath('id', $command->id)
        ->assertJsonPath('state', 'dispatched');

    $this->actingAs($stranger, 'user')->getJson("/v1/commands/{$command->id}")->assertStatus(404);
});

it('lets an admin see all users’ commands for a device', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509200006');
    $member = makeUser('+994509200007');
    $device = makeOpenableDevice($owner, 'SER-H4', '+994700092004');
    $ownerDu = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $memberDu = makeDeviceUser($member, $device, 'user');
    makeOpenCommand($device, $owner, $ownerDu, ['state' => 'dispatched']);
    makeOpenCommand($device, $member, $memberDu, ['state' => 'opened']);

    $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}/commands")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
