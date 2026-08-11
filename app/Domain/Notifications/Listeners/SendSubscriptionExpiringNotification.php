<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;

/**
 * subscription_expiring (INVENTORY §2): the daily reminder sweep fires [SubscriptionExpiringSoon] at each
 * D-30/15/7/1 threshold; this maps the [ReminderKind] to its template and notifies the entitlement owner.
 * Dedupe `sub_expiring:{subscription_id}:{cycle}` where `{cycle}` = the ReminderKind value (LOCKED) → one
 * push per threshold per subscription. The reminder sweep never emits `Expired` here (that terminal is
 * SubscriptionExpired) — an Expired kind is a defensive no-op.
 */
final class SendSubscriptionExpiringNotification extends SubscriptionNotificationListener
{
    public function handle(SubscriptionExpiringSoon $event): void
    {
        $templateKey = match ($event->kind) {
            ReminderKind::D30 => 'subscription.expiring_30d',
            ReminderKind::D15 => 'subscription.expiring_15d',
            ReminderKind::D7 => 'subscription.expiring_7d',
            ReminderKind::D1 => 'subscription.expiring_1d',
            ReminderKind::Expired => null,
        };

        if ($templateKey === null) {
            return;
        }

        $this->notify(
            $event->subscription,
            NotificationType::SubscriptionExpiring,
            $templateKey,
            'sub_expiring:'.$event->subscription->getKey().':'.$event->kind->value,
        );
    }
}
