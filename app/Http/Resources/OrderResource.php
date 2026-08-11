<?php

namespace App\Http\Resources;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 *
 * Maps to openapi components/schemas/Order. Admin-list fields (customer, balances) are only emitted when the
 * relevant relations are eager-loaded, so the mobile list (items only) is unaffected.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'purpose' => $this->purpose->value,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $this->currency,
            'bank_order_id' => $this->bank_order_id,
            'bank_redirect_url' => $this->bank_redirect_url,
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'failed_at' => optional($this->failed_at)->toIso8601String(),
            'failed_reason' => $this->failed_reason,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];

        if ($this->relationLoaded('payer')) {
            $base['customer'] = $this->maskPhone(optional($this->payer)->phone);
        }
        if ($this->relationLoaded('payments')) {
            $captured = (int) $this->payments->where('type', PaymentType::Charge)->where('status', PaymentStatus::Approved)->sum('amount_minor');
            $base['captured_minor'] = $captured;
            $base['refunded_minor'] = (int) abs($this->payments->where('type', PaymentType::Refund)->sum('amount_minor'));
            $base['refundable_minor'] = $this->resource->netCapturedMinor();
        }

        return $base;
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 8) {
            return $phone;
        }

        return substr($phone, 0, 5).str_repeat('*', max(0, strlen($phone) - 7)).substr($phone, -2);
    }
}
