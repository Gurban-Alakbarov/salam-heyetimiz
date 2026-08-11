<?php

namespace App\Domain\Subscriptions\Listeners;

use App\Domain\Payments\Events\OrderPartiallyRefunded;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/** Partial money refund → pro-rata shortening of ends_at (§14.5.1); may fall through to full revoke. */
class AdjustSubscriptionOnOrderPartiallyRefunded
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(OrderPartiallyRefunded $event): void
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

            $this->subscriptions->applyRefund($subscription, (int) $event->refund->amount_minor, $event->order, moneyFull: false);
        }
    }
}
