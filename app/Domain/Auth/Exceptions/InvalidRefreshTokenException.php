<?php

namespace App\Domain\Auth\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Refresh token invalid, revoked, expired, or replayed → 401 (openapi refreshToken 401). */
class InvalidRefreshTokenException extends DomainException
{
    public function httpStatus(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'invalid_refresh_token';
    }

    public function messageKey(): string
    {
        return 'errors.invalid_refresh_token';
    }
}
