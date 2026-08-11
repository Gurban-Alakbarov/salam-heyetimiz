<?php

namespace App\Domain\DeviceComm\Events;

use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An open command was accepted and queued for dispatch. */
class OpenCommandIssued implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly OpenCommand $command) {}

    public function auditAction(): string
    {
        // Admin "Test Relay" commands audit as relay_{open|close}_requested; the mobile open flow
        // keeps its established open.requested action.
        if ($this->command->source === OpenCommandSource::Admin) {
            return 'relay_'.($this->command->direction?->value ?? 'open').'_requested';
        }

        return 'open.requested';
    }

    public function auditPayload(): array
    {
        return [
            'command_id' => $this->command->getKey(),
            'device_id' => $this->command->device_id,
            'user_id' => $this->command->user_id,
            'driver' => $this->command->driver->value,
            'source' => $this->command->source->value,
            'direction' => $this->command->direction?->value,
        ];
    }
}
