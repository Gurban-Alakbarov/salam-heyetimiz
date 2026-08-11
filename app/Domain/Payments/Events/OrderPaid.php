<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Order;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when the bank confirms payment (authoritative getOrderStatus = APPROVED). This is the
 * subscription-activation / device-assignment trigger: the Subscriptions module (batch 06) and
 * Devices module react to this event — Payments does not touch their tables (R-ARCH-06/07).
 */
class OrderPaid implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}

    public function auditAction(): string
    {
        return 'order.paid';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'purpose' => $this->order->purpose->value,
            'amount_minor' => $this->order->amount_minor,
            'paid_at' => optional($this->order->paid_at)->toIso8601String(),
        ];
    }
}
