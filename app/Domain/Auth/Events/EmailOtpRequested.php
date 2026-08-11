<?php

namespace App\Domain\Auth\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An email OTP was issued + dispatched (registration / login / resend). Email masked; code never carried. */
class EmailOtpRequested implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $maskedEmail,
        public readonly string $purpose,
    ) {}

    public function auditAction(): string
    {
        return 'auth.email_otp_requested';
    }

    public function auditPayload(): array
    {
        return ['email' => $this->maskedEmail, 'purpose' => $this->purpose];
    }
}
