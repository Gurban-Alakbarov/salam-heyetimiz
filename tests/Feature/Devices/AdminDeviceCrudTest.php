<?php

use App\Domain\Devices\Models\Device;

/*
| Admin device CRUD: adminCreateDevice, adminListDevices, adminGetDevice, adminUpdateDevice.
*/

it('creates a device as super admin', function () {
    $admin = makeSuperAdmin();
    $model = makeDeviceModel();

    $this->actingAs($admin, 'admin')->postJson('/admin/v1/devices', [
        'serial' => 'ADM-1',
        'device_model_id' => $model->id,
        'sim_phone' => '+994701300001',
        'driver_type' => 'traccar',
    ])->assertStatus(201)->assertJsonPath('serial', 'ADM-1')->assertJsonPath('status', 'unassigned');

    expect(Device::query()->where('serial', 'ADM-1')->exists())->toBeTrue();
});

it('lists devices and filters by status and search query', function () {
    $admin = makeSuperAdmin();
    makeUnassignedDevice('FIND-ME', '+994701300010');
    makeActiveDevice('OTHER-1', '+994701300011');

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/devices?status=unassigned')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'serial', 'status', 'whitelist_capacity_used']], 'page']);

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/devices?q=FIND-ME')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.serial', 'FIND-ME');
});

it('returns admin detail with the roster users', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994506300001');
    $device = makeOwnedDevice($owner, 'ADM-DET-1', '+994701300020');

    $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}")
        ->assertOk()
        ->assertJsonPath('id', $device->id)
        ->assertJsonCount(1, 'users')
        ->assertJsonPath('users.0.role', 'owner')
        ->assertJsonStructure(['users' => [['id', 'user' => ['id', 'phone_masked'], 'role', 'status']], 'whitelist_capacity_used']);
});

it('applies a partial update', function () {
    $admin = makeSuperAdmin();
    $device = makeActiveDevice('ADM-UPD-1', '+994701300030');

    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/devices/{$device->id}", [
        'location_label' => 'New Label',
        'driver_type' => 'sms',
    ])->assertOk()->assertJsonPath('location_label', 'New Label')->assertJsonPath('driver_type', 'sms');

    $device->refresh();
    expect($device->location_label)->toBe('New Label')
        ->and($device->driver_type->value)->toBe('sms');
});

it('requires an admin token', function () {
    $this->getJson('/admin/v1/devices')->assertStatus(401);
});
