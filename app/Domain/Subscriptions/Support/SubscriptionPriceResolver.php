<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;

/**
 * Single source of the recurring subscription price (IMPLEMENTATION_PLAN B2 / BR-13, BR-17, BR-21).
 *
 *  - commercial(): today's terms for a tier — from the admin settings (Ayarlar › Abunəliklər: price per tier,
 *    term in days), falling back to config/domain/subscriptions.php (15 AZN / 365 days) when unset or invalid.
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
        $settings = app(SettingsService::class);
        $price = (int) $settings->value('subscriptions', $tier === SubscriptionTier::Additional ? 'price_additional_minor' : 'price_main_minor');
        $term = (int) $settings->value('subscriptions', 'subscription_duration_days');

        return [
            'price_minor' => $price > 0 ? $price : (int) ($prices[$tier->priceKey()] ?? 0),
            'term_days' => $term > 0 ? $term : (int) config('domain.subscriptions.term_days', 365),
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
