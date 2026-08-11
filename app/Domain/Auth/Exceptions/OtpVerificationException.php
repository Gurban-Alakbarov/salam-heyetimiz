<?php

namespace App\Domain\Auth\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * OTP verification failed → 401. The `code` distinguishes the cause per openapi verifyOtp:
 * wrong_code / otp_expired / otp_max_attempts (R-SEC-03).
 */
class OtpVerificationException extends DomainException
{
    private function __construct(private readonly string $errorCode)
    {
        parent::__construct('OTP verification failed.');
    }

    public static function wrongCode(): self
    {
        return new self('wrong_code');
    }

    public static function expired(): self
    {
        return new self('otp_expired');
    }

    public static function maxAttempts(): self
    {
        return new self('otp_max_attempts');
    }

    public function httpStatus(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return "errors.{$this->errorCode}";
    }
}
