<?php

namespace App\Domain\Devices\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Y6 — a complex-mode device has no owner by design (B4): every resident opens it with their own
 * subscription. Assigning / transferring an owner to it is refused; to give the device an owner, switch it to
 * private mode first (POST /admin/v1/devices/{id}/ownership-mode).
 */
final class DeviceIsComplexModeException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'device_is_complex_mode';
    }

    public function messageKey(): string
    {
        return 'errors.device_is_complex_mode';
    }
}
