<?php

namespace App\Domain\DeviceComm\DTOs;

/** Outcome of a Traccar command send: delivered to a live device, queued (offline), or failed. */
final readonly class TraccarCommandResult
{
    private function __construct(
        public bool $sent,
        public bool $queued,
        public ?string $failureReason,
        public ?string $reference,
    ) {}

    public static function sent(?string $reference = null): self
    {
        return new self(true, false, null, $reference);
    }

    public static function queued(): self
    {
        return new self(false, true, null, null);
    }

    public static function failed(string $failureReason): self
    {
        return new self(false, false, $failureReason, null);
    }
}
