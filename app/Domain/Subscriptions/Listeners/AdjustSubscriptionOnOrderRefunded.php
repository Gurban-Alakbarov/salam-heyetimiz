<?php

namespace App\Domain\Subscriptions\Listeners;

use App\Domain\Payments\Events\OrderRefunded;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/** Full money refund → full subscription revoke (status=refunded, ends_at=now). §14.5.1. */
class AdjustSubscriptionOnOrderRefunded
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(OrderRefunded $event): void
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

            $this->subscriptions->applyRefund($subscription, (int) $event->refund->amount_minor, $event->order, moneyFull: true);
        }
    }
}
