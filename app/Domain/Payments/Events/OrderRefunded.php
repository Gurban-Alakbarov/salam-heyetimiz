<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Full refund settled. The Subscriptions module (batch 06) reacts to apply the §14.5.1
 * subscription impact (status=refunded, ends_at=now); Payments handles only the money here.
 */
class OrderRefunded implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly Refund $refund,
    ) {}

    public function auditAction(): string
    {
        return 'order.refunded';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'refund_id' => $this->refund->id,
            'amount_minor' => $this->refund->amount_minor,
        ];
    }
}
