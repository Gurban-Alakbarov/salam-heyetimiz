<?php

use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Models\TraccarDevice;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;

/*
| TraccarDeviceMapper (v1.2): provisions a device in Traccar and persists the mapping idempotently.
*/

it('registers a device in Traccar and persists the mapping', function () {
    [$device] = openableWithRoster('+994551200001', 'MAP-1', '+994700112001');

    $mapping = app(TraccarDeviceMapper::class)->register($device, 'IMEI-MAP-1');

    expect($mapping->traccar_id)->not->toBeNull()
        ->and($mapping->unique_id)->toBe('IMEI-MAP-1')
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBe((int) $mapping->traccar_id)
        ->and(app(FakeTraccarClient::class)->registered)->toHaveCount(1);
});

it('is idempotent — re-registering does not create a second mapping or Traccar device', function () {
    [$device] = openableWithRoster('+994551200002', 'MAP-2', '+994700112002');
    $mapper = app(TraccarDeviceMapper::class);

    $first = $mapper->register($device, 'IMEI-MAP-2');
    $second = $mapper->register($device, 'IMEI-MAP-2');

    expect($second->id)->toBe($first->id)
        ->and(TraccarDevice::query()->where('device_id', $device->id)->count())->toBe(1)
        ->and(app(FakeTraccarClient::class)->registered)->toHaveCount(1);
});

it('defaults the Traccar uniqueId to the device serial', function () {
    [$device] = openableWithRoster('+994551200003', 'MAP-3', '+994700112003');

    $mapping = app(TraccarDeviceMapper::class)->register($device);

    expect($mapping->unique_id)->toBe('MAP-3');
});

it('adopts an existing Traccar device without creating a new one', function () {
    [$device] = openableWithRoster('+994551200004', 'MAP-4', '+994700112004');

    $mapping = app(TraccarDeviceMapper::class)->adopt($device, 4242, 'IMEI-ADOPT-4');

    expect((int) $mapping->traccar_id)->toBe(4242)
        ->and($mapping->unique_id)->toBe('IMEI-ADOPT-4')
        ->and(app(TraccarDeviceMapper::class)->traccarIdFor($device))->toBe(4242)
        ->and(app(FakeTraccarClient::class)->registered)->toHaveCount(0); // no Traccar device created
});
