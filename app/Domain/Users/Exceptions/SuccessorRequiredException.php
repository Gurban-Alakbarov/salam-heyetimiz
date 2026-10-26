<?php

namespace App\Domain\Users\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Y7 / HIGH-10 — a device owner cannot delete their own account: the barrier would be left without an owner.
 * The device is transferred (support / admin) first; then the account can be deleted.
 */
final class SuccessorRequiredException extends DomainException
{
    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'successor_required';
    }

    public function messageKey(): string
    {
        return 'errors.successor_required';
    }
}
