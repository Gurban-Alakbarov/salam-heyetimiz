<?php

use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\DeviceDiagnostic;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use App\Domain\DeviceComm\Services\TraccarIngestionService;

/*
| Traccar event-forward ingestion (v1.2): records a device_diagnostics row, updates the device online
| status, and confirms a recent dispatched open as opened when the telemetry shows the output fired
| (actuation read-back, R-GSM-03).
*/

it('records a diagnostic and marks the device online from a Traccar forward', function () {
    [$device] = openableWithRoster('+994551100001', 'ING-1', '+994700111001');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-ING-1');

    $matched = app(TraccarIngestionService::class)->ingest([
        'device' => ['uniqueId' => 'IMEI-ING-1', 'status' => 'online'],
        'position' => ['deviceTime' => '2026-06-14T10:00:00Z', 'attributes' => ['rssi' => 22, 'battery' => 88, 'version' => '0.12.8']],
    ]);

    expect($matched)->toBeTrue();

    $device->refresh();
    expect($device->last_online_at)->not->toBeNull()
        ->and($device->last_signal_strength)->toBe(22);

    $diag = DeviceDiagnostic::query()->where('device_id', $device->id)->firstOrFail();
    expect((bool) $diag->online)->toBeTrue()
        ->and($diag->signal_strength)->toBe(22)
        ->and($diag->battery_level)->toBe(88)
        ->and($diag->firmware_version)->toBe('0.12.8');
});

it('confirms a recent dispatched open as opened when the output fired', function () {
    [$device, $user, $du] = openableWithRoster('+994551100002', 'ING-2', '+994700111002');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-ING-2');
    $command = makeOpenCommand($device, $user, $du, ['state' => 'dispatched', 'requested_at' => now()]);

    app(TraccarIngestionService::class)->ingest([
        'device' => ['uniqueId' => 'IMEI-ING-2', 'status' => 'online'],
        'position' => ['deviceTime' => now()->toIso8601String(), 'attributes' => ['output' => 1]],
    ]);

    expect($command->refresh()->state)->toBe(OpenCommandState::Opened)
        ->and($command->completed_at)->not->toBeNull();
});

it('does not confirm when the output is off', function () {
    [$device, $user, $du] = openableWithRoster('+994551100003', 'ING-3', '+994700111003');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-ING-3');
    $command = makeOpenCommand($device, $user, $du, ['state' => 'dispatched', 'requested_at' => now()]);

    app(TraccarIngestionService::class)->ingest([
        'device' => ['uniqueId' => 'IMEI-ING-3', 'status' => 'online'],
        'position' => ['deviceTime' => now()->toIso8601String(), 'attributes' => ['output' => 0]],
    ]);

    expect($command->refresh()->state)->toBe(OpenCommandState::Dispatched);
});

it('ignores a forward for an unknown device', function () {
    $matched = app(TraccarIngestionService::class)->ingest([
        'device' => ['uniqueId' => 'NOT-OURS', 'status' => 'online'],
        'position' => ['attributes' => []],
    ]);

    expect($matched)->toBeFalse()
        ->and(DeviceDiagnostic::query()->count())->toBe(0);
});
