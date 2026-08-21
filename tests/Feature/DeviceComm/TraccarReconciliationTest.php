<?php

use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Exceptions\TraccarDeviceNotFoundException;
use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\DeviceComm\Services\TraccarIngestionService;
use App\Domain\DeviceComm\Services\TraccarReconciliationService;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;

/*
| Reconciliation: adopt a device that already exists in Traccar (e.g. created in the Traccar UI) into
| Laravel — devices row + traccar_devices mapping to the EXISTING Traccar id, never a second Traccar
| device. The webhook design/security model is unchanged (it still never auto-creates from telemetry).
*/

function reconciliationData(string $serial, string $sim): DeviceRegistrationData
{
    $model = makeDeviceModel();

    return new DeviceRegistrationData(
        serial: $serial,
        deviceModelId: (int) $model->id,
        simPhone: $sim,
        driverType: DriverType::Traccar,
    );
}

it('adopts an existing Traccar device into Laravel without duplicating it', function () {
    app(FakeTraccarClient::class)->seedRemoteDevice(77, '868184062169571', 'Sinam Test');

    $result = app(TraccarReconciliationService::class)
        ->reconcile(reconciliationData('SN-REC-1', '+994701400001'), '868184062169571');

    expect($result->created)->toBeTrue()
        ->and((int) $result->mapping->traccar_id)->toBe(77)
        ->and($result->mapping->unique_id)->toBe('868184062169571')
        ->and((int) $result->mapping->device_id)->toBe((int) $result->device->id)
        ->and(Device::query()->whereKey($result->device->id)->exists())->toBeTrue()
        ->and(app(FakeTraccarClient::class)->registered)->toHaveCount(0); // adopted, not created
});

it('refuses to reconcile a uniqueId that does not exist in Traccar (never creates one)', function () {
    expect(fn () => app(TraccarReconciliationService::class)
        ->reconcile(reconciliationData('SN-REC-2', '+994701400002'), 'NOT-IN-TRACCAR'))
        ->toThrow(TraccarDeviceNotFoundException::class);

    expect(Device::query()->where('serial', 'SN-REC-2')->exists())->toBeFalse()
        ->and(TraccarDevice::query()->count())->toBe(0)
        ->and(app(FakeTraccarClient::class)->registered)->toHaveCount(0);
});

it('is idempotent — re-reconciling the same uniqueId returns the same device', function () {
    app(FakeTraccarClient::class)->seedRemoteDevice(88, 'IMEI-IDEM', 'Dev');

    $first = app(TraccarReconciliationService::class)
        ->reconcile(reconciliationData('SN-REC-3', '+994701400003'), 'IMEI-IDEM');
    $second = app(TraccarReconciliationService::class)
        ->reconcile(reconciliationData('SN-REC-3b', '+994701400099'), 'IMEI-IDEM');

    expect($second->created)->toBeFalse()
        ->and((int) $second->device->id)->toBe((int) $first->device->id)
        ->and(TraccarDevice::query()->where('unique_id', 'IMEI-IDEM')->count())->toBe(1)
        ->and(Device::query()->where('serial', 'SN-REC-3b')->exists())->toBeFalse(); // no second device created
});

it('stops the webhook from ignoring telemetry once the device is reconciled', function () {
    app(FakeTraccarClient::class)->seedRemoteDevice(99, 'IMEI-FLOW', 'Flow');
    $ingestion = app(TraccarIngestionService::class);

    $payload = [
        'device' => ['uniqueId' => 'IMEI-FLOW', 'status' => 'online'],
        'position' => ['attributes' => ['sat' => 7]],
    ];

    // Before reconciliation: unknown device → ignored (the bug we are fixing).
    expect($ingestion->ingest($payload))->toBeFalse();

    app(TraccarReconciliationService::class)
        ->reconcile(reconciliationData('SN-REC-4', '+994701400004'), 'IMEI-FLOW');

    // After reconciliation: recognised → ingested (telemetry now flows into device_diagnostics).
    expect($ingestion->ingest($payload))->toBeTrue();
});

it('reconciles via the admin endpoint and the device then appears in the admin list', function () {
    $admin = makeSuperAdmin();
    $model = makeDeviceModel();
    app(FakeTraccarClient::class)->seedRemoteDevice(101, 'IMEI-API', 'API Dev');

    $this->actingAs($admin, 'admin')->postJson('/admin/v1/devices/reconcile', [
        'serial' => 'API-REC-1',
        'device_model_id' => $model->id,
        'sim_phone' => '+994701400010',
        'driver_type' => 'traccar',
        'unique_id' => 'IMEI-API',
    ])->assertStatus(201)->assertJsonPath('serial', 'API-REC-1');

    expect(TraccarDevice::query()->where('unique_id', 'IMEI-API')->where('traccar_id', 101)->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/devices?q=API-REC-1')
        ->assertOk()
        ->assertJsonPath('data.0.serial', 'API-REC-1');
});

it('rejects reconciliation via the endpoint when the device is not in Traccar', function () {
    $admin = makeSuperAdmin();
    $model = makeDeviceModel();

    $this->actingAs($admin, 'admin')->postJson('/admin/v1/devices/reconcile', [
        'serial' => 'API-REC-2',
        'device_model_id' => $model->id,
        'sim_phone' => '+994701400011',
        'driver_type' => 'traccar',
        'unique_id' => 'NOPE-NOT-IN-TRACCAR',
    ])->assertStatus(422);

    expect(Device::query()->where('serial', 'API-REC-2')->exists())->toBeFalse();
});
