<?php

namespace App\Http\Resources;

use App\Domain\Payments\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 *
 * Maps to openapi components/schemas/Payment.
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'type' => $this->type->value,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'card_brand' => $this->card_brand,
            'card_last4' => $this->card_last4,
            'occurred_at' => optional($this->occurred_at)->toIso8601String(),
        ];
    }
}
