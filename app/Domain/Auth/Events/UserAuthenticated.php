<?php

namespace App\Domain\Auth\Events;

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** A mobile user obtained tokens via OTP or refresh. Drives last-login bookkeeping + audit. */
class UserAuthenticated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly UserDevice $device,
        public readonly string $method, // 'otp' | 'refresh'
        public readonly ?string $ip = null,
    ) {}

    public function auditAction(): string
    {
        return 'auth.user_authenticated';
    }

    public function auditPayload(): array
    {
        return [
            'user_id' => $this->user->getKey(),
            'user_device_id' => $this->device->getKey(),
            'method' => $this->method,
        ];
    }
}
