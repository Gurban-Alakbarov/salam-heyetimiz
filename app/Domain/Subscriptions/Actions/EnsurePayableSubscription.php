<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;

/**
 * Owner decision 2026-10-30: someone an admin puts on a device (roster add, owner assignment) must always have a
 * way to pay in the app. Without a subscription row the app had nothing to offer ("abunəliyiniz aktiv deyil" and
 * no button). So a member with no subscription, or a cancelled / refunded one, gets a pending one at today's
 * price; an active, pending or expired row is left alone (expired already has "Yenilə"). The admin can still
 * make it free with "Abunəlik ver" (GrantSubscription activates the pending row).
 */
final class EnsurePayableSubscription
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(DeviceUser $row): Subscription
    {
        $existing = Subscription::query()->where('device_user_id', $row->getKey())->first();
        if ($existing !== null && ! in_array($existing->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Refunded], true)) {
            return $existing;
        }

        return $this->subscriptions->createPending(
            $row,
            $row->family_link_id === null ? SubscriptionTier::Main : SubscriptionTier::Additional,
        );
    }
}
