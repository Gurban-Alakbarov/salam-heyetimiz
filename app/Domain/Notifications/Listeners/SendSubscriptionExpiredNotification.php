<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Subscriptions\Events\SubscriptionExpired;

/**
 * subscription_expired (INVENTORY §2): the daily expiry sweep fires [SubscriptionExpired] once per
 * subscription that lapses; this notifies the entitlement owner. Dedupe `sub_expired:{subscription_id}`
 * (LOCKED) — a single terminal notification per subscription.
 */
final class SendSubscriptionExpiredNotification extends SubscriptionNotificationListener
{
    public function handle(SubscriptionExpired $event): void
    {
        $this->notify(
            $event->subscription,
            NotificationType::SubscriptionExpired,
            'subscription.expired',
            'sub_expired:'.$event->subscription->getKey(),
        );
    }
}
