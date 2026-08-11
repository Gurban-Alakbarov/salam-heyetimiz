<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use App\Domain\Subscriptions\Events\SubscriptionActivated;
use App\Domain\Subscriptions\Models\Subscription;

/**
 * subscription_activated (INVENTORY §2): [SubscriptionActivated] is emitted by BOTH the paid activation
 * (`SubscriptionService::activate`, which first creates the Initial billing period) AND the admin comp
 * grant (`grantManual`, which records NO period). Per LOCKED DECISION, only a real paid activation
 * notifies — the one that created a fresh Initial period funding the CURRENT term. A comp grant has no
 * receipt-worthy period, so it is a safe no-op; a stale prior period is never reused as the activation
 * identity. Dedupe `sub_activated:{subscription_id}:{initial_period_id}` (LOCKED).
 */
final class SendSubscriptionActivatedNotification extends SubscriptionNotificationListener
{
    public function handle(SubscriptionActivated $event): void
    {
        $periodId = $this->freshPaidActivationPeriodId($event->subscription);
        if ($periodId === null) {
            return; // comp grant (no billing period) or indeterminate → no activation notification.
        }

        $this->notify(
            $event->subscription,
            NotificationType::SubscriptionActivated,
            'subscription.activated',
            'sub_activated:'.$event->subscription->getKey().':'.$periodId,
        );
    }

    /**
     * The Initial period id iff it funds the subscription's CURRENT term — i.e. a paid activation just
     * created it (its `period_end` equals the subscription's `ends_at`). Returns null for a comp grant
     * (no Initial period, or an Initial period whose term the grant has since extended past).
     */
    private function freshPaidActivationPeriodId(Subscription $subscription): ?int
    {
        $initial = $subscription->periods()
            ->where('kind', SubscriptionPeriodKind::Initial->value)
            ->orderByDesc('id')
            ->first();

        if ($initial === null || $initial->period_end === null || $subscription->ends_at === null) {
            return null;
        }

        return $initial->period_end->format('Y-m-d H:i:s') === $subscription->ends_at->format('Y-m-d H:i:s')
            ? (int) $initial->getKey()
            : null;
    }
}
