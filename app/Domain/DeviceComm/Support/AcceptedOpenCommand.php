<?php

namespace App\Domain\DeviceComm\Support;

use App\Domain\DeviceComm\Models\OpenCommand;

/** Assembled openDevice result for the OpenCommandAccepted envelope. */
final readonly class AcceptedOpenCommand
{
    public function __construct(
        public OpenCommand $command,
        public int $expectedCompletionMs,
        public bool $driverConfirmsActuation,
        public string $websocketChannel,
        public bool $replayed,
    ) {}
}
