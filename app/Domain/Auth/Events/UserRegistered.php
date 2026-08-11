<?php

namespace App\Domain\Auth\Events;

use App\Domain\Users\Models\User;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A user completed email verification for the first time (registration). Email is masked (R-SEC-15). */
class UserRegistered implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly string $maskedEmail,
    ) {}

    public function auditAction(): string
    {
        return 'auth.user_registered';
    }

    public function auditPayload(): array
    {
        return ['user_id' => $this->user->getKey(), 'email' => $this->maskedEmail];
    }
}
