<?php

namespace App\Domain\DeviceComm\Events;

use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An open command reached a terminal state. The optimisation-only WebSocket broadcast (R-ARCH-12) and
 * the audit listener (batch 09 audit) consume this; clients poll /v1/commands/{id} as the fallback.
 */
class OpenCommandCompleted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly OpenCommand $command) {}

    public function auditAction(): string
    {
        // Admin "Test Relay" commands audit as relay_{open|close}_{success|dispatched|failed}; the
        // mobile open flow keeps its established open.completed action.
        if ($this->command->source === OpenCommandSource::Admin) {
            $direction = $this->command->direction?->value ?? 'open';
            $outcome = match ($this->command->state) {
                OpenCommandState::Opened => 'success',
                OpenCommandState::Dispatched => 'dispatched',
                default => 'failed',
            };

            return 'relay_'.$direction.'_'.$outcome;
        }

        return 'open.completed';
    }

    public function auditPayload(): array
    {
        return [
            'command_id' => $this->command->getKey(),
            'device_id' => $this->command->device_id,
            'user_id' => $this->command->user_id,
            'state' => $this->command->state->value,
            'direction' => $this->command->direction?->value,
            'failure_reason' => $this->command->failure_reason,
            'latency_ms' => $this->command->latency_ms,
            'response' => data_get($this->command->metadata, 'response'),
        ];
    }
}
