<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A failed admin login step (bad password, lockout, or failed 2FA). Email is the identifier. */
class AdminLoginFailed implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'admin.login_failed';
    }

    public function auditPayload(): array
    {
        return ['email' => $this->email, 'reason' => $this->reason];
    }
}
