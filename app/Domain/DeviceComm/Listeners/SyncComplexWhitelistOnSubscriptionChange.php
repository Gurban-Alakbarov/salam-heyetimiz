<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\DeviceComm\Services\ComplexWhitelistReconciler;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Events\SubscriptionActivated;
use App\Domain\Subscriptions\Events\SubscriptionCancelled;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Events\SubscriptionRefunded;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;

/**
 * B7: on complex-mode devices the whitelist follows the resident's own subscription (paid → add,
 * expired / cancelled / refunded → remove). Private devices are ignored by the reconciler.
 */
class SyncComplexWhitelistOnSubscriptionChange
{
    public function __construct(private readonly ComplexWhitelistReconciler $reconciler) {}

    public function handle(SubscriptionActivated|SubscriptionRenewed|SubscriptionExpired|SubscriptionCancelled|SubscriptionRefunded $event): void
    {
        $deviceUser = DeviceUser::query()->find($event->subscription->device_user_id);
        if ($deviceUser !== null) {
            $this->reconciler->reconcile($deviceUser);
        }
    }
}
