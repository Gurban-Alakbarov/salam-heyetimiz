<?php

namespace App\Domain\DeviceComm\DTOs;

/**
 * Outcome of a driver open attempt. `dispatched` means the command reached the device path;
 * `actuated` means the driver observed real actuation (only confirming drivers — R-GSM-03). The
 * concrete drivers that produce these land in the GSM batch; the core consumes this shape.
 */
final readonly class DriverOpenResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public bool $dispatched,
        public bool $actuated,
        public ?string $failureReason,
        public ?int $latencyMs,
        public array $metadata,
    ) {}

    /** CLIP-style success: reached the device but actuation is not observable (terminal `dispatched`). */
    public static function dispatched(?int $latencyMs = null, array $metadata = []): self
    {
        return new self(true, false, null, $latencyMs, $metadata);
    }

    /** Confirmed open: the driver observed actuation (terminal `opened`). */
    public static function opened(?int $latencyMs = null, array $metadata = []): self
    {
        return new self(true, true, null, $latencyMs, $metadata);
    }

    public static function failed(string $failureReason, ?int $latencyMs = null, array $metadata = []): self
    {
        return new self(false, false, $failureReason, $latencyMs, $metadata);
    }
}
