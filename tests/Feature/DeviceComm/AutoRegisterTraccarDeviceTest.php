<?php

use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use App\Domain\Devices\Models\Device;

/*
| Registering a device must provision it in Traccar in the same breath. Skipping this is invisible and
| expensive: Traccar silently rejects an unknown uniqueId, so the unit dials in forever without ever
| coming online and a human has to notice and reconcile it by hand (it bit prod three times).
| QUEUE_CONNECTION=sync in tests, so the queued job runs inline here.
*/

/** Create a device through the real admin endpoint, exactly as the technician does. */
function createDeviceViaAdmin(string $serial, string $sim, string $driver = 'traccar'): Device
{
    $admin = makeSuperAdmin();
    $model = makeDeviceModel();

    test()->actingAs($admin, 'admin')->postJson('/admin/v1/devices', [
        'serial' => $serial,
        'device_model_id' => $model->id,
        'sim_phone' => $sim,
        'driver_type' => $driver,
    ])->assertStatus(201);

    return Device::query()->where('serial', $serial)->firstOrFail();
}

it('provisions a newly registered device in Traccar automatically', function () {
    $device = createDeviceViaAdmin('AUTO-TR-1', '+994701400001');

    // Created in Traccar under its uniqueId (= the IMEI the technician typed into serial)…
    expect(app(FakeTraccarClient::class)->registered)->toHaveCount(1)
        ->and(app(FakeTraccarClient::class)->registered[0]['uniqueId'])->toBe('AUTO-TR-1');

    // …and mapped locally, so the driver can address it and the webhook can find it.
    $mapping = TraccarDevice::query()->where('device_id', $device->id)->first();
    expect($mapping)->not->toBeNull()
        ->and($mapping->unique_id)->toBe('AUTO-TR-1')
        ->and($mapping->traccar_id)->not->toBeNull()
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBe((int) $mapping->traccar_id);
});

it('adopts a device that already exists in Traccar instead of creating a duplicate', function () {
    // The unit was created in the Traccar UI first — registering the same uniqueId again is rejected.
    app(FakeTraccarClient::class)->seedRemoteDevice(777, 'AUTO-TR-2', 'Created in Traccar UI');

    $device = createDeviceViaAdmin('AUTO-TR-2', '+994701400002');

    expect(app(FakeTraccarClient::class)->registered)->toBeEmpty() // no duplicate create
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBe(777);
});

it('is idempotent — an already linked device is never re-registered', function () {
    $device = createDeviceViaAdmin('AUTO-TR-3', '+994701400003');
    $traccarId = app(TraccarDeviceMapper::class)->traccarIdFor($device);

    // Re-running the provisioning (retry / replay) must not touch Traccar again.
    App\Domain\DeviceComm\Jobs\RegisterTraccarDeviceJob::dispatchSync((int) $device->id);

    expect(app(FakeTraccarClient::class)->registered)->toHaveCount(1)
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBe($traccarId)
        ->and(TraccarDevice::query()->where('device_id', $device->id)->count())->toBe(1);
});

it('leaves non-Traccar devices alone (SMS transport never lives in Traccar)', function () {
    $device = createDeviceViaAdmin('AUTO-TR-4', '+994701400004', driver: 'sms');

    expect(app(FakeTraccarClient::class)->registered)->toBeEmpty()
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBeNull();
});
