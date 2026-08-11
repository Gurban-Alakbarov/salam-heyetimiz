<?php

namespace App\Domain\Payments\Enums;

enum PaymentLogDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
}
