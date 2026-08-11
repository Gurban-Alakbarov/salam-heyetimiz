<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Bank transport failure / outage → 503 (OpenAPI PaymentProviderUnavailable). */
class PaymentProviderUnavailableException extends DomainException
{
    public function httpStatus(): int
    {
        return 503;
    }

    public function errorCode(): string
    {
        return 'payment_provider_unavailable';
    }

    public function messageKey(): string
    {
        return 'errors.payment_provider_unavailable';
    }
}
