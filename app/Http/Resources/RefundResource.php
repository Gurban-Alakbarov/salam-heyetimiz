<?php

namespace App\Http\Resources;

use App\Domain\Payments\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 *
 * Maps to openapi components/schemas/Refund. Enriched for the admin Refund History (order + customer + admin
 * + bank refund id) — relation fields are null when not eager-loaded.
 */
class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->whenLoaded('order') instanceof \App\Domain\Payments\Models\Order ? $this->order : null;

        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'order_reference' => $order?->reference,
            'bank_order_id' => $order?->bank_order_id,
            'bank_refund_id' => $this->bank_refund_id,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $order?->currency ?? 'AZN',
            'reason' => $this->reason,
            'status' => $this->status->value,
            'requested_by_admin_id' => (int) $this->requested_by_admin_id,
            'requested_by' => optional($this->whenLoaded('requestedByAdmin'))->email,
            'processed_by_admin_id' => $this->processed_by_admin_id !== null ? (int) $this->processed_by_admin_id : null,
            'processed_by' => optional($this->whenLoaded('processedByAdmin'))->email,
            'customer' => $order?->relationLoaded('payer') ? $this->maskPhone($order->payer?->phone) : null,
            'linked_payment_id' => $this->linked_payment_id !== null ? (int) $this->linked_payment_id : null,
            'error_message' => $this->error_message,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'duration_seconds' => ($this->completed_at !== null && $this->created_at !== null) ? abs($this->completed_at->diffInSeconds($this->created_at)) : null,
            'raw_response' => $this->rawResponse(),
        ];
    }

    private function rawResponse(): mixed
    {
        if (! $this->resource->relationLoaded('linkedPayment') || $this->linkedPayment === null) {
            return null;
        }
        $raw = $this->linkedPayment->raw_response_encrypted; // 'encrypted' cast decrypts on access

        return $raw !== null ? (json_decode((string) $raw, true) ?? $raw) : null;
    }

    private function maskPhone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) < 6) {
            return $phone;
        }

        return substr($phone, 0, 5).str_repeat('*', max(0, strlen($phone) - 7)).substr($phone, -2);
    }
}
