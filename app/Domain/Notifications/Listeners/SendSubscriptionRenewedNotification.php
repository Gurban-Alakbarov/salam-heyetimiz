<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;

/**
 * subscription_renewed (INVENTORY §2): [SubscriptionRenewed] is emitted by `SubscriptionService::renew`,
 * which always creates the Renewal billing period BEFORE dispatching — so the latest Renewal period is
 * this renewal. Notifies the entitlement owner. Dedupe `sub_renewed:{subscription_id}:{period_id}`
 * (LOCKED) using that Renewal period id; a defensive no-op if none is present.
 */
final class SendSubscriptionRenewedNotification extends SubscriptionNotificationListener
{
    public function handle(SubscriptionRenewed $event): void
    {
        $period = $event->subscription->periods()
            ->where('kind', SubscriptionPeriodKind::Renewal->value)
            ->orderByDesc('id')
            ->first();

        if ($period === null) {
            return;
        }

        $this->notify(
            $event->subscription,
            NotificationType::SubscriptionRenewed,
            'subscription.renewed',
            'sub_renewed:'.$event->subscription->getKey().':'.$period->getKey(),
        );
    }
}
