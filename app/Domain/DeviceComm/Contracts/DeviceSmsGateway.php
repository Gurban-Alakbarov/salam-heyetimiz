<?php

namespace App\Domain\DeviceComm\Contracts;

/**
 * Sends a device command via SMS to a device SIM (the open fallback path — R-GSM-04/10). Distinct from
 * the Auth OtpTransport (user OTP); this carries UMKa command strings (e.g. `OUTPUT0=1`) to the device,
 * pre-authorised on the device via `AUTH` (UMKa §3.22). Concrete AZ provider selected in Phase 0.
 */
interface DeviceSmsGateway
{
    public function sendCommand(string $phone, string $command): bool;
}
