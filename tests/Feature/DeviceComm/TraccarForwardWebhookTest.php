<?php

use App\Domain\DeviceComm\Models\DeviceDiagnostic;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;

/*
| Traccar event-forward webhook (POST /v1/traccar/forward — traccarForward). Shared-secret token gate
| (Traccar does not sign its forwards), then ingestion. Always 200 on a known input to avoid retry storms.
*/

beforeEach(function () {
    config(['integrations.traccar.forward_token' => 'forward-secret']);
});

it('rejects a forward without the shared token', function () {
    $this->postJson('/v1/traccar/forward', ['device' => ['uniqueId' => 'x']])
        ->assertStatus(401);
});

it('rejects a forward with the wrong token', function () {
    $this->withHeaders(['X-Traccar-Token' => 'nope'])
        ->postJson('/v1/traccar/forward', ['device' => ['uniqueId' => 'x']])
        ->assertStatus(401);
});

it('accepts a forward with the valid header token and ingests it', function () {
    [$device] = openableWithRoster('+994551300001', 'WH-1', '+994700113001');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-WH-1');

    $this->withHeaders(['X-Traccar-Token' => 'forward-secret'])
        ->postJson('/v1/traccar/forward', [
            'device' => ['uniqueId' => 'IMEI-WH-1', 'status' => 'online'],
            'position' => ['deviceTime' => '2026-06-14T09:00:00Z', 'attributes' => ['rssi' => 19]],
        ])
        ->assertOk();

    expect(DeviceDiagnostic::query()->where('device_id', $device->id)->count())->toBe(1);
});

it('accepts the token via the query string as well', function () {
    [$device] = openableWithRoster('+994551300002', 'WH-2', '+994700113002');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-WH-2');

    $this->postJson('/v1/traccar/forward?token=forward-secret', [
        'device' => ['uniqueId' => 'IMEI-WH-2', 'status' => 'online'],
        'position' => ['attributes' => ['rssi' => 17]],
    ])->assertOk();

    expect(DeviceDiagnostic::query()->where('device_id', $device->id)->count())->toBe(1);
});
