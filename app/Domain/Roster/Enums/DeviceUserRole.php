<?php

namespace App\Domain\Roster\Enums;

enum DeviceUserRole: string
{
    case Owner = 'owner';
    case User = 'user';
}
