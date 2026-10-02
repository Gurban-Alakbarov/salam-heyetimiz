<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/**
 * BR-18 (IMPLEMENTATION_PLAN B5, shared with B8): when a resident / family member loses access to a device,
 * their live subscription on it is CANCELLED — never refunded automatically (a refund is a separate admin
 * action through the existing refund flow). Paid history (subscription_periods, orders, payments) is never
 * deleted. Every cancellation is audited. Already-ended rows are left as they are.
 */
final class CancelOnAccessRemoval
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  array<string, mixed>  $context  extra audit payload (who removed, from where) */
    public function handle(DeviceUser $deviceUser, string $reason, array $context = []): ?Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = Subscription::query()->where('device_user_id', $deviceUser->getKey())->first();
        if ($subscription === null
            || ! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PendingPayment], true)) {
            return null;
        }

        $previous = $subscription->status->value;
        $this->subscriptions->cancel($subscription, $reason);

        $this->audit->record('subscription.cancelled_on_removal', $context + [
            'subscription_id' => (int) $subscription->id,
            'device_user_id' => (int) $deviceUser->getKey(),
            'device_id' => (int) $deviceUser->device_id,
            'beneficiary_user_id' => (int) $deviceUser->user_id,
            'previous_status' => $previous,
            'reason' => $reason,
            'refund' => 'none', // BR-18: no automatic refund
        ], Subscription::class, (int) $subscription->id);

        return $subscription;
    }
}
