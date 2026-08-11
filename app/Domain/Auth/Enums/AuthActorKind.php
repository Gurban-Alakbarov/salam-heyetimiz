<?php

namespace App\Domain\Auth\Enums;

enum AuthActorKind: string
{
    case User = 'user';
    case Admin = 'admin';
}
