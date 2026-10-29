<?php

namespace App\Domain\Payments\Support;

use App\Domain\Payments\DTOs\OrderItemData;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;

/**
 * Server-side pricing for order items (R-DOM-09). Prices are integer minor units (qəpik). Item amounts are
 * never trusted from the client. Subscription prices come from SubscriptionPriceResolver (admin settings,
 * config fallback); the device-sale default stays in config/domain/subscriptions.php.
 */
final class OrderPricing
{
    public function __construct(private readonly ?SubscriptionPriceResolver $subscriptions = null) {}

    /**
     * Unit price for a concrete order line (IMPLEMENTATION_PLAN B2). Subscription lines are priced by the
     * Subscriptions module's resolver against the referenced subscription (initial = its snapshot, renewal =
     * current commercial price; never a 0 amount). The device-sale line is unchanged (config default) and the
     * per-device sale price is never read here (BR-20).
     */
    public function unitPriceFor(OrderItemData $item): int
    {
        if ($item->itemType === OrderItemType::Device) {
            return $this->unitPriceMinor(OrderItemType::Device);
        }

        $subscription = $item->referencedId !== null ? Subscription::query()->find($item->referencedId) : null;

        return ($this->subscriptions ?? new SubscriptionPriceResolver())->chargeMinorFor($subscription, $item->itemType);
    }

    public function unitPriceMinor(OrderItemType $type): int
    {
        $prices = (array) config('domain.subscriptions.default_prices_minor', []);
        $resolver = $this->subscriptions ?? new SubscriptionPriceResolver();

        return match ($type) {
            OrderItemType::Device => (int) ($prices['device_sale'] ?? 13500),
            OrderItemType::SubMain, OrderItemType::SubRenewal => $resolver->commercial(SubscriptionTier::Main)['price_minor'],
            OrderItemType::SubAdditional => $resolver->commercial(SubscriptionTier::Additional)['price_minor'],
        };
    }

    public function descriptionFor(OrderItemType $type): string
    {
        return match ($type) {
            OrderItemType::Device => 'Cihaz satışı',
            OrderItemType::SubMain => 'Əsas istifadəçi aboneliyi',
            OrderItemType::SubAdditional => 'Əlavə istifadəçi aboneliyi',
            OrderItemType::SubRenewal => 'Abonelik yenilənməsi',
        };
    }
}
