<?php

namespace App\Domain\Subscriptions\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Auto-renew cannot be enabled: tokenization is not yet available, or no active card token was
 * supplied → 409 (openapi toggleAutoRenew 409). Auto-charge is P2 (Tech Spec §13.8).
 */
class AutoRenewUnavailableException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'auto_renew_unavailable';
    }

    public function messageKey(): string
    {
        return 'errors.auto_renew_unavailable';
    }
}
