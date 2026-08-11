<?php

namespace App\Domain\Users\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case SelfDeleted = 'self_deleted';
}
