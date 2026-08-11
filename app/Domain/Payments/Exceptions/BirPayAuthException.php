<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** OAuth token acquisition against BirPay failed. Carries the exact upstream message (never a generic one). */
class BirPayAuthException extends DomainException
{
    public function __construct(string $message, private readonly ?string $upstreamError = null)
    {
        parent::__construct($message);
    }

    public function upstreamError(): ?string
    {
        return $this->upstreamError;
    }

    public function httpStatus(): int
    {
        return 502;
    }

    public function errorCode(): string
    {
        return 'payment_auth_failed';
    }

    public function messageKey(): string
    {
        return 'errors.payment_auth_failed';
    }
}
