<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\Refund;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class OrderRefundRequested implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}

    public function auditAction(): string
    {
        return 'order.refund_requested';
    }

    public function auditPayload(): array
    {
        return [
            'refund_id' => $this->refund->id,
            'order_id' => $this->refund->order_id,
            'amount_minor' => $this->refund->amount_minor,
            'reason' => $this->refund->reason,
        ];
    }
}
