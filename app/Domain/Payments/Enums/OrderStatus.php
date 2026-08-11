<?php

namespace App\Domain\Payments\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Authorising = 'authorising';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case Expired = 'expired';

    /** A settled order whose money has been captured (refundable). */
    public function isPaidLike(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyRefunded], true);
    }

    /** No further automatic transitions expected. */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Paid, self::Failed, self::Cancelled,
            self::Refunded, self::PartiallyRefunded, self::Expired,
        ], true);
    }

    /** Awaiting a bank outcome. */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Pending, self::Authorising], true);
    }
}
