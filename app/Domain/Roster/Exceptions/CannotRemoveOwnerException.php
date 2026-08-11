<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** The owner cannot be removed via roster removal — re-owner via transfer or decommission instead → 409. */
class CannotRemoveOwnerException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'cannot_remove_owner';
    }

    public function messageKey(): string
    {
        return 'errors.cannot_remove_owner';
    }
}
