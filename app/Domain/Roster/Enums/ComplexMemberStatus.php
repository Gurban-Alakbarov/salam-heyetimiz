<?php

namespace App\Domain\Roster\Enums;

enum ComplexMemberStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
