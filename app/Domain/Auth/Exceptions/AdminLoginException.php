<?php

namespace App\Domain\Auth\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Admin authentication failed → 401. `code` per openapi adminLogin / adminVerify2fa:
 * invalid_credentials / account_locked / wrong_totp / wrong_recovery_code /
 * recovery_code_already_used / challenge_expired (R-SEC-05/06).
 */
class AdminLoginException extends DomainException
{
    private function __construct(private readonly string $errorCode, private readonly int $status = 401)
    {
        parent::__construct('Admin authentication failed.');
    }

    public static function invalidCredentials(): self
    {
        return new self('invalid_credentials');
    }

    public static function accountLocked(): self
    {
        return new self('account_locked');
    }

    public static function challengeExpired(): self
    {
        return new self('challenge_expired');
    }

    public static function wrongTotp(): self
    {
        return new self('wrong_totp');
    }

    public static function wrongRecoveryCode(): self
    {
        return new self('wrong_recovery_code');
    }

    public function httpStatus(): int
    {
        return $this->status;
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
