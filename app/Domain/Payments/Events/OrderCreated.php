<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Order;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCreated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}

    public function auditAction(): string
    {
        return 'order.created';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'purpose' => $this->order->purpose->value,
            'amount_minor' => $this->order->amount_minor,
        ];
    }
}
