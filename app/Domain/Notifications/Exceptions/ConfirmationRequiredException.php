<?php

namespace App\Domain\Notifications\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * An admin campaign send was submitted without `confirmed=true` (mandatory send confirmation, ADMIN_SPEC
 * D8) → 409. Nothing is created; the caller must re-submit with confirmation.
 */
class ConfirmationRequiredException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'confirmation_required';
    }

    public function messageKey(): string
    {
        return 'errors.confirmation_required';
    }
}
