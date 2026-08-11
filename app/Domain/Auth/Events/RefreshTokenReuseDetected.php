<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A revoked (already-rotated) refresh token was presented again — a theft signal. The
 * install's token family has been revoked; this surfaces it for audit/alerting (R-SEC-02).
 */
class RefreshTokenReuseDetected implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly int $userDeviceId,
    ) {}

    public function auditAction(): string
    {
        return 'auth.refresh_token_reuse_detected';
    }

    public function auditPayload(): array
    {
        return ['user_id' => $this->userId, 'user_device_id' => $this->userDeviceId];
    }
}
