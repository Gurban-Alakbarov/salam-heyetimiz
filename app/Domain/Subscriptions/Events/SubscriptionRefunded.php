<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a refund's subscription impact is applied (§14.5.1). `fullRefund` distinguishes a
 * full revoke (status=refunded) from a pro-rata shortening (status stays active). DeviceComm
 * reacts to whitelist-remove on a full revoke.
 */
class SubscriptionRefunded implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $refundAmountMinor,
        public readonly bool $fullRefund,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.refunded';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'refund_amount_minor' => $this->refundAmountMinor,
            'full_refund' => $this->fullRefund,
            'ends_at' => optional($this->subscription->ends_at)->toIso8601String(),
        ];
    }
}
