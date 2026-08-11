<?php

namespace App\Domain\Payments\Support;

use App\Domain\Payments\Enums\OrderItemType;

/**
 * Server-side pricing for order items (R-DOM-09). Prices are integer minor units (qəpik) sourced
 * from config/domain/subscriptions.php default_prices_minor. Item amounts are never trusted from
 * the client. (sub_renewal is priced as the main-tier annual fee; tier-aware renewal pricing is
 * finalised by the Subscriptions module in batch 06.)
 */
final class OrderPricing
{
    public function unitPriceMinor(OrderItemType $type): int
    {
        $prices = (array) config('domain.subscriptions.default_prices_minor', []);

        return match ($type) {
            OrderItemType::Device => (int) ($prices['device_sale'] ?? 13500),
            OrderItemType::SubMain => (int) ($prices['sub_main'] ?? 1200),
            OrderItemType::SubAdditional => (int) ($prices['sub_additional'] ?? 600),
            OrderItemType::SubRenewal => (int) ($prices['sub_main'] ?? 1200),
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
