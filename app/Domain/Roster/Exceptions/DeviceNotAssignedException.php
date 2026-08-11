<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Residents can only be added to a device that already has an owner (is assigned) → 409. */
class DeviceNotAssignedException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'device_not_assigned';
    }

    public function messageKey(): string
    {
        return 'errors.device_not_assigned';
    }
}
