<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin revoked their current session (openapi adminLogout). */
class AdminLoggedOut implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly int $adminId) {}

    public function auditAction(): string
    {
        return 'admin.logged_out';
    }

    public function auditPayload(): array
    {
        return ['admin_id' => $this->adminId];
    }
}
