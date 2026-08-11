<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Callback HMAC signature missing or invalid → 401 (OpenAPI paymentCallback 401). */
class SignatureInvalidException extends DomainException
{
    public function httpStatus(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'signature_invalid';
    }

    public function messageKey(): string
    {
        return 'errors.signature_invalid';
    }
}
