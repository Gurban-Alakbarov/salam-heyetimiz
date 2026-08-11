<?php

namespace App\Domain\DeviceComm\Actions;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Events\OpenCommandIssued;
use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Domain\DeviceComm\Services\OpenCommandService;

/**
 * Admin "Test Relay" entry point (Phase 4). Issues an open/close relay command straight into the
 * existing DeviceComm pipeline — no roster/subscription/cooldown gate (that is the mobile OpenDevice
 * flow). Everything downstream is reused verbatim: open_commands row (source=admin) →
 * DispatchOpenCommandJob → CommandDispatcher → driver → state machine → history. OpenCommandIssued
 * drives the relay_{direction}_requested audit via the wildcard audit listener.
 */
final class DispatchRelayTest
{
    public function __construct(
        private readonly DriverResolver $drivers,
        private readonly OpenCommandService $commands,
    ) {}

    public function handle(Device $device, CommandDirection $direction, ?string $clientIp): OpenCommand
    {
        $command = $this->commands->issueAdmin(
            device: $device,
            driver: $this->drivers->driverTypeFor($device),
            direction: $direction,
            clientIp: $clientIp,
            metadata: ['requested_via' => 'admin_relay_test'],
        );

        DispatchOpenCommandJob::dispatch((int) $command->getKey());
        OpenCommandIssued::dispatch($command);

        return $command;
    }
}
