<?php

namespace App\Domain\Auth\Events;

use App\Domain\Admin\Models\AdminUser;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin completed two-factor auth and received a session token. */
class AdminAuthenticated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly AdminUser $admin,
        public readonly bool $usedRecoveryCode,
        public readonly ?string $ip = null,
    ) {}

    public function auditAction(): string
    {
        return 'admin.authenticated';
    }

    public function auditPayload(): array
    {
        return [
            'admin_id' => $this->admin->getKey(),
            'used_recovery_code' => $this->usedRecoveryCode,
        ];
    }
}
