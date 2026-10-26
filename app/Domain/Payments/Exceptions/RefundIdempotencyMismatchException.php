<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** O5 — the admin reused a refund Idempotency-Key for another order or amount → 409 (nothing is refunded). */
class RefundIdempotencyMismatchException extends DomainException
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
