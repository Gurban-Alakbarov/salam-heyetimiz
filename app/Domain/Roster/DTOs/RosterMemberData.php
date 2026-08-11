<?php

namespace App\Domain\Roster\DTOs;

use App\Domain\Roster\Enums\DeviceUserRole;
use Spatie\LaravelData\Data;

/** A resident to add to a device roster — canonical by phone (R-DOM-01). Owners are bound via assign, not here. */
class RosterMemberData extends Data
{
    public function __construct(
        public readonly string $phone,
        public readonly DeviceUserRole $role = DeviceUserRole::User,
    ) {}
}
