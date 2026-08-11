<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\PaymentCallback;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentCallbackProcessed implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly PaymentCallback $callback) {}

    public function auditAction(): string
    {
        return 'payment.callback_processed';
    }

    public function auditPayload(): array
    {
        return [
            'callback_id' => $this->callback->id,
            'outcome' => $this->callback->outcome->value,
        ];
    }
}
