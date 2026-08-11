<?php

namespace App\Domain\Payments\Events;

use App\Domain\Payments\Models\PaymentCallback;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a signature-verified, de-duplicated callback is persisted. A listener decides whether
 * to verify-and-apply (terminal) or schedule a recheck (PENDING).
 */
class PaymentCallbackReceived implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly PaymentCallback $callback) {}

    public function auditAction(): string
    {
        return 'payment.callback_received';
    }

    public function auditPayload(): array
    {
        return [
            'callback_id' => $this->callback->id,
            'bank_order_id' => $this->callback->bank_order_id,
            'bank_status' => $this->callback->bank_status,
        ];
    }
}
