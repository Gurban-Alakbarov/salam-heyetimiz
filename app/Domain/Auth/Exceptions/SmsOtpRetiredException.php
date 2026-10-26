<?php

namespace App\Domain\Auth\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * K6 — the legacy phone-OTP sign-in (POST /v1/auth/otp/request|verify) is retired → 410. It provisioned an
 * account from a bare phone number (no email, none of the registration checks); the app signs in by email
 * OTP only (bootstrap flag sms_login=false).
 */
final class SmsOtpRetiredException extends DomainException
{
    public function httpStatus(): int
    {
        return 410;
    }

    public function errorCode(): string
    {
        return 'sms_otp_retired';
    }

    public function messageKey(): string
    {
        return 'errors.sms_otp_retired';
    }
}
