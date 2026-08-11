<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * An explicit capability boundary: refund/cancel against BirPay are delivered in a later phase
 * (IMPLEMENTATION_ORDER Phase 5/6). This is not a placeholder — it refuses honestly rather than faking a
 * result. It is unreachable in normal operation until that phase enables the path.
 */
class BirPayCapabilityNotEnabledException extends DomainException
{
    public function httpStatus(): int
    {
        return 501;
    }

    public function errorCode(): string
    {
        return 'payment_capability_not_enabled';
    }

    public function messageKey(): string
    {
        return 'errors.payment_capability_not_enabled';
    }
}
