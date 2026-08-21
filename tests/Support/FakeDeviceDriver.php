<?php

namespace Tests\Support;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Contracts\DeviceDriver;
use App\Domain\DeviceComm\DTOs\DriverDiagnosticResult;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\DTOs\DriverSyncResult;
use App\Domain\DeviceComm\Models\OpenCommand;

/**
 * Test-only DeviceDriver double (the production CLIP/SMS/MQTT drivers land in the GSM batch). Lets the
 * DeviceComm-core tests exercise the dispatcher state machine + fallback without real GSM hardware —
 * the same pattern as FakeKapitalGateway / FakeOtpTransport (R-ARCH-08).
 */
final class FakeDeviceDriver implements DeviceDriver
{
    public int $openCalls = 0;

    public function __construct(private readonly DriverOpenResult $result) {}

    public function open(Device $device, OpenCommand $command): DriverOpenResult
    {
        $this->openCalls++;

        return $this->result;
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
        return new DriverDiagnosticResult(online: true);
    }

    public function supports(string $capability): bool
    {
        return true;
    }
}
