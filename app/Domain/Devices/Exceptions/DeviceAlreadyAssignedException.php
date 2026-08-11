<?php

namespace App\Domain\Devices\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** A device that already has an owner cannot be re-assigned (use transfer) → 409. */
class DeviceAlreadyAssignedException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'device_already_assigned';
    }

    public function messageKey(): string
    {
        return 'errors.device_already_assigned';
    }
}
