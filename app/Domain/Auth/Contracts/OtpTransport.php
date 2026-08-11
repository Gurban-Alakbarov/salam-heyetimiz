<?php

namespace App\Domain\Auth\Contracts;

/**
 * Delivers an OTP code to a phone (R-ARCH-08). The concrete AZ SMS provider is selected
 * in Phase 0; the in-memory fake is the default test/dev binding. Codes are passed in
 * clear to the transport only — never persisted in clear and never logged (R-SEC-03/15).
 */
interface OtpTransport
{
    public function send(string $phone, string $code, string $locale): void;
}
