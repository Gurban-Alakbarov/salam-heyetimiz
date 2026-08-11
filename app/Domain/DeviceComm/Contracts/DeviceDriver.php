<?php

namespace App\Domain\DeviceComm\Contracts;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\DTOs\DriverDiagnosticResult;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\DTOs\DriverSyncResult;
use App\Domain\DeviceComm\Models\OpenCommand;

/**
 * The modular device-communication driver (R-GSM-01). Adding a protocol implements this interface and
 * registers it in the container under `device-driver.<type>` — no core changes required. The v1.2
 * transport drivers are Traccar (primary) and SMS (emergency fallback); BLE is reserved/deferred.
 * The core depends only on this contract.
 */
interface DeviceDriver
{
    /** Issue an open against the device for the caller's command. */
    public function open(Device $device, OpenCommand $command): DriverOpenResult;

    /** Program a phone into the device whitelist. */
    public function whitelistAdd(Device $device, string $phone): DriverSyncResult;

    /** Remove a phone from the device whitelist. */
    public function whitelistRemove(Device $device, string $phone): DriverSyncResult;

    /** Probe device health. */
    public function diagnose(Device $device): DriverDiagnosticResult;

    /** Whether the driver supports a named capability (e.g. 'actuation_confirmation', 'whitelist'). */
    public function supports(string $capability): bool;
}
