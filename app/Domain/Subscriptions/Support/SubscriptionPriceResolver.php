<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;

/**
 * Single source of the recurring subscription price (IMPLEMENTATION_PLAN B2 / BR-13, BR-17, BR-21).
 *
 *  - commercial(): today's terms for a tier — 12 AZN / 30 days for main AND additional (config).
 *  - chargeMinorFor(): what an order line for a subscription must cost.
 *      · pending_payment (an initial purchase) → its price snapshot (taken at createPending)
 *      · anything else (a renewal)            → current commercial price — so a legacy comp
 *        (price_minor = 0) or an old annual row is never renewed at its stale snapshot
 *      · a non-positive amount is never charged — falls back to the commercial price.
 *
 * The one-off device sale price (devices.sale_price_minor) is deliberately NOT an input here.
 */
final class SubscriptionPriceResolver
{
    /** @return array{price_minor:int, term_days:int, currency:string} */
    public function commercial(SubscriptionTier $tier): array
    {
        $prices = (array) config('domain.subscriptions.default_prices_minor', []);

        return [
            'price_minor' => (int) ($prices[$tier->priceKey()] ?? 0),
            'term_days' => (int) config('domain.subscriptions.term_days', 30),
            'currency' => (string) config('domain.subscriptions.currency', 'AZN'),
        ];
    }

    public function chargeMinorFor(?Subscription $subscription, OrderItemType $type): int
    {
        if ($subscription === null) {
            return $this->commercial($this->tierFor($type))['price_minor'];
        }

        $commercial = $this->commercial($subscription->tier)['price_minor'];

        if ($subscription->status === SubscriptionStatus::PendingPayment && (int) $subscription->price_minor > 0) {
            return (int) $subscription->price_minor;
        }

        return $commercial;
    }

    private function tierFor(OrderItemType $type): SubscriptionTier
    {
        return $type === OrderItemType::SubAdditional ? SubscriptionTier::Additional : SubscriptionTier::Main;
    }
}
