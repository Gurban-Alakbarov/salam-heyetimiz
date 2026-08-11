<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin regenerated their recovery-code set (the previous set was invalidated). */
class RecoveryCodesRegenerated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly int $adminId) {}

    public function auditAction(): string
    {
        return 'admin.recovery_codes_regenerated';
    }

    public function auditPayload(): array
    {
        return ['admin_id' => $this->adminId];
    }
}
