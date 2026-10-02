<?php

namespace App\Domain\Roster\Enums;

enum FamilyLinkStatus: string
{
    case Active = 'active';
    case Removed = 'removed';
}
