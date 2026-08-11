<?php

namespace App\Domain\Subscriptions\Enums;

enum SubscriptionPeriodKind: string
{
    case Initial = 'initial';
    case Renewal = 'renewal';
    case Extension = 'extension';
    case Refund = 'refund';

    /** Kinds that represent a paid term funding a "latest order" (HIGH-18 derivation). */
    public function isFunding(): bool
    {
        return in_array($this, [self::Initial, self::Renewal, self::Extension], true);
    }
}
