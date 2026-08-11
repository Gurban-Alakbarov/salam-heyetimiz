<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Order;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class OrderAuthorising implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}

    public function auditAction(): string
    {
        return 'order.authorising';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'bank_order_id' => $this->order->bank_order_id,
        ];
    }
}
