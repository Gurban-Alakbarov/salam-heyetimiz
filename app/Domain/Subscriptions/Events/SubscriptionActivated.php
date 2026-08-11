<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a pending subscription is first activated by a paid order. The DeviceComm module
 * (batch 07) reacts to whitelist-add the user; Notifications (batch 08) sends the receipt.
 */
class SubscriptionActivated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription) {}

    public function auditAction(): string
    {
        return 'subscription.activated';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'device_user_id' => $this->subscription->device_user_id,
            'tier' => $this->subscription->tier->value,
            'ends_at' => optional($this->subscription->ends_at)->toIso8601String(),
        ];
    }
}
