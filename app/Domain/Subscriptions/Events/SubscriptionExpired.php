<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the daily expiry sweep (Tech Spec §13.5). DeviceComm (batch 07) reacts to remove the
 * user from the device whitelist and to re-derive device suspension; Notifications notifies.
 */
class SubscriptionExpired implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription) {}

    public function auditAction(): string
    {
        return 'subscription.expired';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'device_user_id' => $this->subscription->device_user_id,
        ];
    }
}
