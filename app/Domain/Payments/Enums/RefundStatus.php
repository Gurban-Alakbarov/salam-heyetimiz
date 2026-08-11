<?php

namespace App\Domain\Payments\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
