<?php

namespace App\Domain\Payments\Enums;

enum CardTokenStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
