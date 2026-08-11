<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** No active roster row for this user on this device → 404. */
class RosterMemberNotFoundException extends DomainException
{
    public function httpStatus(): int
    {
        return 404;
    }

    public function errorCode(): string
    {
        return 'roster_member_not_found';
    }

    public function messageKey(): string
    {
        return 'errors.roster_member_not_found';
    }
}
