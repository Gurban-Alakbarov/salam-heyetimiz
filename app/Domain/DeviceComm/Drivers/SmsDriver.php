<?php

namespace App\Domain\DeviceComm\Drivers;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Contracts\DeviceDriver;
use App\Domain\DeviceComm\Contracts\DeviceSmsGateway;
use App\Domain\DeviceComm\DTOs\DriverDiagnosticResult;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\DTOs\DriverSyncResult;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Models\OpenCommand;

/**
 * Emergency fallback transport (v1.2): sends the relay-pulse command to the device SIM by SMS
 * (`OUTPUT0=1`; sender pre-authorised on the device via `AUTH` at provisioning — UMKa §3.22). SMS is
 * fire-and-forget, so it terminates at `dispatched` (R-GSM-03). No device-side whitelist (server-auth).
 */
final class SmsDriver implements DeviceDriver
{
    public function __construct(private readonly DeviceSmsGateway $sms) {}

    public function open(Device $device, OpenCommand $command): DriverOpenResult
    {
        if ($device->sim_phone === null || $device->sim_phone === '') {
            return DriverOpenResult::failed('no_sim_phone');
        }

        $commandText = $this->commandText($device, $command->direction ?? CommandDirection::Open);

        $ok = $this->sms->sendCommand((string) $device->sim_phone, $commandText);

        return $ok
            ? DriverOpenResult::dispatched(metadata: ['command' => $commandText])
            : DriverOpenResult::failed('sms_failed', metadata: ['command' => $commandText]);
    }

    /** Model-driven command text (open_command / close_command by direction), falling back to config. */
    private function commandText(Device $device, CommandDirection $direction): string
    {
        $model = $device->model;
        $fromModel = $direction === CommandDirection::Close
            ? $model?->close_command
            : ($model?->open_command ?? $model?->sms_open_command);

        if (is_string($fromModel) && $fromModel !== '') {
            return $fromModel;
        }

        return $direction === CommandDirection::Close
            ? (string) config('domain.device_comm.close_command', 'OUTPUT0=0')
            : (string) config('domain.device_comm.open_command', 'OUTPUT0=1');
    }

    public function whitelistAdd(Device $device, string $phone): DriverSyncResult
    {
        return DriverSyncResult::ok();
    }

    public function whitelistRemove(Device $device, string $phone): DriverSyncResult
    {
        return DriverSyncResult::ok();
    }

    public function diagnose(Device $device): DriverDiagnosticResult
    {
        return new DriverDiagnosticResult(online: false); // SMS path cannot probe health
    }

    public function supports(string $capability): bool
    {
        return $capability === 'open';
    }
}
