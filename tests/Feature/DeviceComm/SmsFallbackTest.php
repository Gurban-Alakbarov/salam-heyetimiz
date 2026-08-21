<?php

use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\DeviceComm\Adapters\FakeDeviceSmsGateway;
use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Drivers\SmsDriver;
use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Services\CommandDispatcher;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;

/*
| End-to-end transport fallback (R-GSM-04, v1.2): the real TraccarDriver reports the device offline
| (transient) and the dispatcher falls back to the real SmsDriver, which sends OUTPUT0=1 to the SIM.
*/

it('falls back to the real SMS driver when Traccar reports the device offline', function () {
    [$device, $user, $du] = openableWithRoster('+994552000001', 'FB-1', '+994700120001');
    DeviceModel::query()->whereKey($device->device_model_id)->update(['fallback_open_driver' => 'sms']);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-FB-1');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::queued()); // offline → device_offline (transient)
    $command = makeOpenCommand($device, $user, $du); // driver snapshot = traccar

    app(CommandDispatcher::class)->dispatch($command);

    expect($command->refresh()->state)->toBe(OpenCommandState::Dispatched); // SMS is fire-and-forget

    $sms = app(FakeDeviceSmsGateway::class);
    expect($sms->sent)->toHaveKey($device->sim_phone)
        ->and($sms->sent[$device->sim_phone])->toBe('OUTPUT0=1');
});

it('fails with no_sim_phone when the SMS driver has no SIM to address', function () {
    [$device, $user, $du] = openableWithRoster('+994552000002', 'FB-2', '+994700120002');
    $command = makeOpenCommand($device, $user, $du);
    $device->sim_phone = ''; // in-memory only — the column is NOT NULL + unique

    $result = app(SmsDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeFalse()
        ->and($result->failureReason)->toBe('no_sim_phone');
});
