<?php

use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommandAttempt;
use App\Domain\DeviceComm\Services\CommandDispatcher;
use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeDeviceDriver;

/*
| Command dispatch lifecycle (R-GSM-03/04/06). With no concrete driver registered the command fails
| with driver_unsupported; a test driver double exercises the success/confirmation/fallback paths.
*/

function dispatchableCommand(string $phoneSeed, string $serial, string $sim): array
{
    $user = makeUser($phoneSeed);
    $device = makeOpenableDevice($user, $serial, $sim);
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $user->id)->firstOrFail();

    return [$device, $user, $du];
}

it('fails with driver_unsupported when no driver is registered', function () {
    Event::fake([OpenCommandCompleted::class]);
    [$device, $user, $du] = dispatchableCommand('+994509100001', 'SER-D1', '+994700091001');
    // `ble` is reserved/deferred (R-GSM-01) → no `device-driver.ble` binding exists.
    $device->update(['driver_type' => 'ble']);
    $command = makeOpenCommand($device, $user, $du); // driver snapshot = ble

    app(CommandDispatcher::class)->dispatch($command);

    $command->refresh();
    expect($command->state)->toBe(OpenCommandState::Failed)
        ->and($command->failure_reason)->toBe('driver_unsupported')
        ->and(OpenCommandAttempt::query()->where('open_command_id', $command->id)->where('failure_reason', 'driver_unsupported')->count())->toBe(1);

    Event::assertDispatched(OpenCommandCompleted::class);
});

it('reaches dispatched when a driver accepts but cannot confirm actuation (R-GSM-03)', function () {
    [$device, $user, $du] = dispatchableCommand('+994509100002', 'SER-D2', '+994700091002');
    // sms is the emergency fallback and cannot confirm actuation → even an "opened" driver result
    // terminates at dispatched (R-GSM-03).
    $device->update(['driver_type' => 'sms']);
    app()->instance('device-driver.sms', new FakeDeviceDriver(DriverOpenResult::opened(1500)));
    $command = makeOpenCommand($device, $user, $du); // driver snapshot = sms

    app(CommandDispatcher::class)->dispatch($command);

    $command->refresh();
    expect($command->state)->toBe(OpenCommandState::Dispatched)
        ->and($command->latency_ms)->toBe(1500)
        ->and($command->attempts)->toBe(1);
});

it('reaches opened for a confirming driver (Traccar)', function () {
    [$device, $user, $du] = dispatchableCommand('+994509100003', 'SER-D3', '+994700091003');
    // default driver = traccar, which confirms actuation from telemetry output state.
    app()->instance('device-driver.traccar', new FakeDeviceDriver(DriverOpenResult::opened(2000)));
    $command = makeOpenCommand($device, $user, $du); // driver snapshot = traccar

    app(CommandDispatcher::class)->dispatch($command);

    expect($command->refresh()->state)->toBe(OpenCommandState::Opened);
});

it('falls back once to the model fallback driver on a transient failure (R-GSM-04)', function () {
    [$device, $user, $du] = dispatchableCommand('+994509100004', 'SER-D4', '+994700091004');
    DeviceModel::query()->whereKey($device->device_model_id)->update(['fallback_open_driver' => 'sms']);

    app()->instance('device-driver.traccar', new FakeDeviceDriver(DriverOpenResult::failed('device_offline')));
    app()->instance('device-driver.sms', new FakeDeviceDriver(DriverOpenResult::dispatched(4200)));
    $command = makeOpenCommand($device, $user, $du); // driver = traccar

    app(CommandDispatcher::class)->dispatch($command);

    $command->refresh();
    expect($command->state)->toBe(OpenCommandState::Dispatched)
        ->and(OpenCommandAttempt::query()->where('open_command_id', $command->id)->count())->toBe(2)
        ->and(OpenCommandAttempt::query()->where('open_command_id', $command->id)->where('attempt_no', 2)->where('driver', 'sms')->where('state', 'dispatched')->exists())->toBeTrue();
});

it('does not fall back on a non-transient failure', function () {
    [$device, $user, $du] = dispatchableCommand('+994509100005', 'SER-D5', '+994700091005');
    DeviceModel::query()->whereKey($device->device_model_id)->update(['fallback_open_driver' => 'sms']);

    app()->instance('device-driver.traccar', new FakeDeviceDriver(DriverOpenResult::failed('driver_unsupported')));
    $smsDriver = new FakeDeviceDriver(DriverOpenResult::dispatched());
    app()->instance('device-driver.sms', $smsDriver);
    $command = makeOpenCommand($device, $user, $du);

    app(CommandDispatcher::class)->dispatch($command);

    expect($command->refresh()->state)->toBe(OpenCommandState::Failed)
        ->and($smsDriver->openCalls)->toBe(0)
        ->and(OpenCommandAttempt::query()->where('open_command_id', $command->id)->count())->toBe(1);
});

it('runs through the queued job', function () {
    [$device, $user, $du] = dispatchableCommand('+994509100006', 'SER-D6', '+994700091006');
    $command = makeOpenCommand($device, $user, $du);

    (new DispatchOpenCommandJob($command->id))->handle(app(CommandDispatcher::class));

    // traccar driver resolves but the device has no Traccar mapping → failed(device_not_registered).
    expect($command->refresh()->state)->toBe(OpenCommandState::Failed);
});
