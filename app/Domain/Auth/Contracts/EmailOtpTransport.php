<?php

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Enums\OtpPurpose;

/**
 * Delivers an OTP code to an email address (registration + email login). Mirrors the SMS-side
 * {@see OtpTransport} but is its own contract so OtpService can inject both transports cleanly and
 * tests can swap an in-memory fake. The concrete prod impl renders the OTP via the generic
 * TemplatedMailer (Brevo SMTP). Codes are passed in clear to the transport only — never persisted in
 * clear, never logged (R-SEC-03/15).
 */
interface EmailOtpTransport
{
    public function send(string $email, string $code, string $locale, OtpPurpose $purpose): void;
}
