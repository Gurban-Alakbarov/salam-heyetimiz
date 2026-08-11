<?php

namespace App\Domain\Auth\Enums;

enum RefreshTokenRevocationReason: string
{
    case Rotated = 'rotated';
    case Logout = 'logout';
    case PasswordChange = 'password_change';
    case Admin = 'admin';
    case Security = 'security';
    case Expired = 'expired';
}
