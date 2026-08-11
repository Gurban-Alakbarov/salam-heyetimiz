<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A mobile user revoked their current install's refresh token (openapi logout). */
class UserLoggedOut implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $userDeviceId,
    ) {}

    public function auditAction(): string
    {
        return 'auth.user_logged_out';
    }

    public function auditPayload(): array
    {
        return ['user_id' => $this->userId, 'user_device_id' => $this->userDeviceId];
    }
}
