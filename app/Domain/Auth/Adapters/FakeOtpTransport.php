<?php

namespace App\Domain\Auth\Adapters;

use App\Domain\Auth\Contracts\OtpTransport;

/**
 * Test/dev OTP transport (R-ARCH-08): keeps the last code per phone in memory so feature
 * tests can read it back instead of reaching an SMS provider. Registered as a singleton.
 * Never used outside the testing/fake-provider binding.
 */
final class FakeOtpTransport implements OtpTransport
{
    /** @var array<string, string> phone => last code */
    private array $sent = [];

    public function send(string $phone, string $code, string $locale): void
    {
        $this->sent[$phone] = $code;
    }

    public function lastCodeFor(string $phone): ?string
    {
        return $this->sent[$phone] ?? null;
    }
}
