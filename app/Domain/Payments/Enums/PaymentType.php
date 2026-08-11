<?php

namespace App\Domain\Payments\Enums;

enum PaymentType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case Reversal = 'reversal';
}
