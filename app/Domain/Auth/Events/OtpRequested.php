<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An OTP was issued + dispatched. Phone is masked; the code is never carried (R-SEC-15). */
class OtpRequested implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $maskedPhone,
        public readonly string $purpose,
    ) {}

    public function auditAction(): string
    {
        return 'auth.otp_requested';
    }

    public function auditPayload(): array
    {
        return ['phone' => $this->maskedPhone, 'purpose' => $this->purpose];
    }
}
