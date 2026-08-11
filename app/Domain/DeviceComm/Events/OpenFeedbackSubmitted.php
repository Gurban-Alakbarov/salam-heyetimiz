<?php

namespace App\Domain\DeviceComm\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A user reported whether the gate actually moved after a CLIP-only open (CRIT-06). */
class OpenFeedbackSubmitted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $commandId,
        public readonly int $userId,
        public readonly bool $gateMoved,
    ) {}

    public function auditAction(): string
    {
        return 'open.feedback_submitted';
    }

    public function auditPayload(): array
    {
        return ['command_id' => $this->commandId, 'user_id' => $this->userId, 'gate_moved' => $this->gateMoved];
    }
}
