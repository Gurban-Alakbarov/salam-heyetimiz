<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Approved = 'approved';
    case Declined = 'declined';
    case Reversed = 'reversed';
    case Pending = 'pending';
    case Error = 'error';
}
