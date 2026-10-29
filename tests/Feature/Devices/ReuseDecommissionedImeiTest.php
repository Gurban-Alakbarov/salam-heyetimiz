<?php

use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\Devices\Models\Device;

/*
| Admin audit phase 3 (2026-10-29): a decommissioned device keeps its row for history, but its IMEI and SIM can be
| registered again (uniqueness counts active devices only). Two ACTIVE devices with one IMEI / SIM stay impossible.
*/

function registerDevice(string $serial, string $sim)
{
    return test()->postJson('/admin/v1/devices', [
        'serial' => $serial,
        'device_model_id' => makeDeviceModel('ReuseVendor', 'RV-'.uniqid())->id,
        'sim_phone' => $sim,
        'driver_type' => 'traccar',
    ]);
}

it('registers a new device with the IMEI and SIM of a decommissioned one', function () {
    $this->actingAs(makeSuperAdmin(), 'admin');
    $oldId = registerDevice('REUSE-IMEI-1', '+994701900001')->assertCreated()->json('id');
    $this->deleteJson("/admin/v1/devices/{$oldId}", ['reason' => 'wrong IMEI typed'])->assertStatus(204);

    $newId = registerDevice('REUSE-IMEI-1', '+994701900001')->assertCreated()->json('id');

    expect($newId)->not->toBe($oldId)
        ->and(Device::withTrashed()->where('serial', 'REUSE-IMEI-1')->count())->toBe(2)
        ->and(Device::query()->where('serial', 'REUSE-IMEI-1')->count())->toBe(1)
        // the old device's Traccar mapping was released for the new one
        ->and(TraccarDevice::query()->where('device_id', $oldId)->exists())->toBeFalse();
});

it('still refuses a second ACTIVE device with the same IMEI or SIM', function () {
    $this->actingAs(makeSuperAdmin(), 'admin');
    registerDevice('REUSE-IMEI-2', '+994701900002')->assertCreated();

    registerDevice('REUSE-IMEI-2', '+994701900003')->assertStatus(422)->assertJsonValidationErrors('serial', 'error.fields');
    registerDevice('REUSE-IMEI-3', '+994701900002')->assertStatus(422)->assertJsonValidationErrors('sim_phone', 'error.fields');
});
