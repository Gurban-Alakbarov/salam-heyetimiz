<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Order;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class OrderFailed implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'order.failed';
    }

    public function auditPayload(): array
    {
        return [
            'order_id' => $this->order->id,
            'reason' => $this->reason,
        ];
    }
}
