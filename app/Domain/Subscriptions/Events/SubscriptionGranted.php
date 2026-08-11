<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An admin activated/extended a subscription WITHOUT a payment (comp grant). Distinct from
 * SubscriptionActivated (which the grant also emits so DeviceComm/Notifications react exactly as they
 * do for a paid activation) — this one exists purely so the audit trail records that no money was
 * involved and WHICH admin granted it.
 */
class SubscriptionGranted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $deviceId,
        public readonly int $userId,
        public readonly ?int $adminId,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.granted';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'device_user_id' => $this->subscription->device_user_id,
            'device_id' => $this->deviceId,
            'user_id' => $this->userId,
            'granted_by_admin_id' => $this->adminId,
            'tier' => $this->subscription->tier->value,
            'term_days' => $this->subscription->term_days,
            'ends_at' => optional($this->subscription->ends_at)->toIso8601String(),
            'paid' => false,
        ];
    }
}
