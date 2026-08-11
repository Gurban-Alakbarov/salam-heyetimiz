<?php

namespace App\Domain\Auth\Adapters;

use App\Domain\Auth\Contracts\EmailOtpTransport;
use App\Domain\Auth\Enums\OtpPurpose;

/**
 * Test/dev email-OTP transport: keeps the last code per email in memory so feature tests can read it
 * back instead of reaching SMTP. Registered as a singleton in the testing environment. Mirrors
 * {@see FakeOtpTransport}.
 */
final class FakeEmailOtpTransport implements EmailOtpTransport
{
    /** @var array<string, string> email => last code */
    private array $sent = [];

    /** @var array<string, OtpPurpose> email => last purpose */
    private array $purposes = [];

    public function send(string $email, string $code, string $locale, OtpPurpose $purpose): void
    {
        $this->sent[$email] = $code;
        $this->purposes[$email] = $purpose;
    }

    public function lastCodeFor(string $email): ?string
    {
        return $this->sent[$email] ?? null;
    }

    public function lastPurposeFor(string $email): ?OtpPurpose
    {
        return $this->purposes[$email] ?? null;
    }
}
