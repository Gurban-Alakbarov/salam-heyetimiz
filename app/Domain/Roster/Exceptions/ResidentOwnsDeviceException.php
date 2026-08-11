<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * The resident still OWNS at least one device, so deleting the account would orphan that barrier.
 * Transfer the device to another owner (or decommission it) first → 409.
 */
class ResidentOwnsDeviceException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'resident_owns_device';
    }

    public function messageKey(): string
    {
        return 'errors.resident_owns_device';
    }
}
