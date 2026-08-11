<?php

namespace App\Domain\Roster\Enums;

enum DeviceUserStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
