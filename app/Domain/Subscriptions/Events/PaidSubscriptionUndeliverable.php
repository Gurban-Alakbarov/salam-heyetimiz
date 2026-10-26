<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * K3 — a paid order line funded a subscription that can no longer be served (access removed / device gone).
 * The subscription is left as it is (never revived); Payments flags the order for a manual refund by
 * finance (decision 2026-10-26: no automatic refunds).
 */
class PaidSubscriptionUndeliverable implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly Subscription $subscription,
        public readonly int $amountMinor,
        public readonly string $reason,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.payment_undeliverable';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => (int) $this->order->id,
            'order_reference' => $this->order->reference,
            'subscription_id' => (int) $this->subscription->id,
            'subscription_status' => $this->subscription->status->value,
            'amount_minor' => $this->amountMinor,
            'reason' => $this->reason,
        ];
    }
}
