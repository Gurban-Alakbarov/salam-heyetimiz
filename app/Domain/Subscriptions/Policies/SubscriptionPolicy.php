<?php

namespace App\Domain\Subscriptions\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Subscriptions\Models\Subscription;
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

    public function renew(mixed $actor, Subscription $subscription): bool
    {
        return $this->isOwnerUser($actor, $subscription);
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
