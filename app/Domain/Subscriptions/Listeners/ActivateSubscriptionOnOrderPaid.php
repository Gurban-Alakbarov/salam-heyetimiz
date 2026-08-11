<?php

namespace App\Domain\Subscriptions\Listeners;

use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/**
 * Activates (initial) or renews (subsequent) the subscription(s) a paid order funded. The order's
 * sub_* items reference subscription_id; this is the "subscription activation trigger" consumed from
 * the Payments OrderPaid event (R-ARCH-07). Device-sale items are handled by the Devices module.
 */
class ActivateSubscriptionOnOrderPaid
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(OrderPaid $event): void
    {
        $items = $event->order->items()
            ->whereIn('item_type', ['sub_main', 'sub_additional', 'sub_renewal'])
            ->get();

        foreach ($items as $item) {
            if ($item->referenced_id === null) {
                continue;
            }

            $subscription = Subscription::query()->find($item->referenced_id);
            if ($subscription === null) {
                continue;
            }

            $this->subscriptions->applyFromPaidOrder($subscription, $event->order, (int) $item->total_amount_minor);
        }
    }
}
