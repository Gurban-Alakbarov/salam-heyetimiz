<?php

namespace App\Domain\DeviceComm\DTOs;

/** Outcome of a driver diagnose() probe (persisted as device_diagnostics in the GSM batch). */
final readonly class DriverDiagnosticResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $online,
        public ?int $signalStrength = null,
        public ?int $batteryLevel = null,
        public ?string $firmwareVersion = null,
        public array $raw = [],
    ) {}
}
