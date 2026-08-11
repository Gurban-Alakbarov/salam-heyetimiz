<?php

namespace App\Domain\DeviceComm\DTOs;

/** Outcome of a driver whitelist add/remove (consumed by the WhitelistSyncJob in the GSM batch). */
final readonly class DriverSyncResult
{
    private function __construct(
        public bool $success,
        public ?string $failureReason,
    ) {}

    public static function ok(): self
    {
        return new self(true, null);
    }

    public static function failed(string $failureReason): self
    {
        return new self(false, $failureReason);
    }
}
