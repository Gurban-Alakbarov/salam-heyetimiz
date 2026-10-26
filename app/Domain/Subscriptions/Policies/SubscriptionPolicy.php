<?php

namespace App\Domain\Subscriptions\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\SubscriptionPaymentAuthorizer;
use App\Domain\Users\Models\User;

/**
 * A mobile user may view/renew/toggle only their own subscription (the device_user's user);
 * admins may view any (SubscriptionPolicy; openapi renewSubscription x-authorization = payer).
 */
class SubscriptionPolicy
{
    public function view(mixed $actor, Subscription $subscription): bool
    {
        if ($actor instanceof AdminUser) {
            return true;
        }

        return $this->isOwnerUser($actor, $subscription);
    }

    /**
     * O2 — whoever may PAY for the subscription may renew it (SubscriptionPaymentAuthorizer, B7/B8): the
     * beneficiary, the head of the active family link it was granted through, the private device owner. Before,
     * only the beneficiary could, so a family head could not renew a member's expired subscription. Whether the
     * subscription is still deliverable is checked when the order is created (K3).
     */
    public function renew(mixed $actor, Subscription $subscription): bool
    {
        return $actor instanceof User && app(SubscriptionPaymentAuthorizer::class)->canPay($actor, $subscription);
    }

    public function toggleAutoRenew(mixed $actor, Subscription $subscription): bool
    {
        return $this->isOwnerUser($actor, $subscription);
    }

    private function isOwnerUser(mixed $actor, Subscription $subscription): bool
    {
        return $actor instanceof User
            && (int) $subscription->deviceUser()->value('user_id') === (int) $actor->getKey();
    }
}
