<?php

use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Drivers\TraccarDriver;
use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;

/*
| TraccarDriver (R-GSM-01, v1.2 primary transport). open() sends OUTPUT0=1 via the Traccar REST custom
| command and maps the send outcome to the driver result. Confirmation to `opened` happens later via the
| event-forward ingestion (see TraccarIngestionTest).
*/

it('dispatches an open via Traccar when the device is mapped and online', function () {
    [$device, $user, $du] = openableWithRoster('+994551000001', 'TR-1', '+994700110001');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-TR-1');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-1'));
    $command = makeOpenCommand($device, $user, $du);

    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeTrue()
        ->and($result->actuated)->toBeFalse() // confirmation arrives later via telemetry
        ->and($result->metadata['traccar_command'])->toBe('cmd-1')
        ->and(app(FakeTraccarClient::class)->lastCommand())->toBe('OUTPUT0=1');
});

it('returns a transient device_offline failure when Traccar queues the command', function () {
    [$device, $user, $du] = openableWithRoster('+994551000002', 'TR-2', '+994700110002');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-TR-2');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::queued());
    $command = makeOpenCommand($device, $user, $du);

    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeFalse()
        ->and($result->failureReason)->toBe('device_offline'); // transient → dispatcher falls back to SMS
});

it('fails with device_not_registered when the device has no Traccar mapping', function () {
    [$device, $user, $du] = openableWithRoster('+994551000003', 'TR-3', '+994700110003');
    $command = makeOpenCommand($device, $user, $du);

    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeFalse()
        ->and($result->failureReason)->toBe('device_not_registered');
});

it('reports the actuation_confirmation capability', function () {
    $driver = app(TraccarDriver::class);

    expect($driver->supports('actuation_confirmation'))->toBeTrue()
        ->and($driver->supports('open'))->toBeTrue()
        ->and($driver->supports('nonsense'))->toBeFalse();
});
