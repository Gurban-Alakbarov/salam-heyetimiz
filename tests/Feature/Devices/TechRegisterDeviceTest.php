<?php

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceRegistered;
use App\Domain\Devices\Models\Device;
use Illuminate\Support\Facades\Event;

/*
| POST /v1/technical/devices — techRegisterDevice (technical mobile mode, admin JWT).
*/

function registerPayload(int $modelId, string $serial = 'SN-1000', string $sim = '+994701110001'): array
{
    return [
        'serial' => $serial,
        'device_model_id' => $modelId,
        'sim_phone' => $sim,
        'driver_type' => 'traccar',
        'firmware_version' => '1.2.3',
    ];
}

it('registers a new device in unassigned state', function () {
    Event::fake([DeviceRegistered::class]);
    $tech = makeTechnicalAdmin();
    $model = makeDeviceModel();

    $response = $this->actingAs($tech, 'admin')
        ->postJson('/v1/technical/devices', registerPayload($model->id, 'SN-1001', '+994701110011'));

    $response->assertStatus(201)
        ->assertJsonPath('serial', 'SN-1001')
        ->assertJsonPath('status', 'unassigned')
        ->assertJsonPath('device_model.id', $model->id)
        ->assertJsonStructure(['id', 'serial', 'sim_phone', 'driver_type', 'status', 'whitelist_capacity_used']);

    $device = Device::query()->where('serial', 'SN-1001')->firstOrFail();
    expect($device->status)->toBe(DeviceStatus::Unassigned)
        ->and($device->registered_by_admin_id)->toBe($tech->id);

    Event::assertDispatched(DeviceRegistered::class);
});

it('rejects a duplicate serial', function () {
    $tech = makeTechnicalAdmin();
    $model = makeDeviceModel();
    makeUnassignedDevice('DUP-1', '+994701110021');

    $this->actingAs($tech, 'admin')
        ->postJson('/v1/technical/devices', registerPayload($model->id, 'DUP-1', '+994701110022'))
        ->assertStatus(422);
});

it('rejects an unknown device model', function () {
    $tech = makeTechnicalAdmin();

    $this->actingAs($tech, 'admin')
        ->postJson('/v1/technical/devices', registerPayload(999999, 'SN-1002', '+994701110031'))
        ->assertStatus(422);
});

it('requires an admin token (a user token is rejected)', function () {
    $user = makeUser('+994506200001');
    $model = makeDeviceModel();

    $this->withHeaders(bearer(userAccessToken($user)))
        ->postJson('/v1/technical/devices', registerPayload($model->id))
        ->assertStatus(401);
});
