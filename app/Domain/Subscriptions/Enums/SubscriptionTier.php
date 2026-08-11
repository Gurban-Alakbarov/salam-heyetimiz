<?php

namespace App\Domain\Subscriptions\Enums;

use App\Domain\Payments\Enums\OrderItemType;

enum SubscriptionTier: string
{
    case Main = 'main';
    case Additional = 'additional';

    /** The order item type used to price a renewal of this tier (tier-aware renewal pricing). */
    public function renewalItemType(): OrderItemType
    {
        return match ($this) {
            self::Main => OrderItemType::SubMain,
            self::Additional => OrderItemType::SubAdditional,
        };
    }

    /** Settings key (config/domain/subscriptions.default_prices_minor) for this tier. */
    public function priceKey(): string
    {
        return match ($this) {
            self::Main => 'sub_main',
            self::Additional => 'sub_additional',
        };
    }
}
