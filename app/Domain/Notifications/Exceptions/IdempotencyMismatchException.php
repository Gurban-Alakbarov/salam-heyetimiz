<?php

namespace App\Domain\Notifications\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * The same Idempotency-Key was reused with a different request body (OpenAPI IdempotencyKeyRequired) → 409.
 * Guards against a changed campaign silently reusing a prior key's cached result.
 */
class IdempotencyMismatchException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'idempotency_mismatch';
    }

    public function messageKey(): string
    {
        return 'errors.idempotency_mismatch';
    }
}
