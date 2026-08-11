<?php

namespace App\Http\Resources;

use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Http\Request;

/**
 * @mixin Order
 *
 * Order + payments + refunds + a professional, enriched timeline + a consolidated payment_detail block for the
 * admin back-office. Balances + bank_order_id come from the base OrderResource (payments are eager-loaded).
 */
class OrderDetailResource extends OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return array_merge(parent::toArray($request), [
            'payment_detail' => $this->paymentDetail($order),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
            'webhooks' => $order->callbacks->map(static fn ($c): array => [
                'id' => (int) $c->id,
                'bank_status' => $c->bank_status,
                'outcome' => $c->outcome->value,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'processed_at' => optional($c->processed_at)->toIso8601String(),
            ])->all(),
            'timeline' => $this->timeline($order),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentDetail(Order $order): array
    {
        $settings = app(PaymentSettings::class);
        /** @var Payment|null $charge */
        $charge = $order->payments->where('type', PaymentType::Charge)->sortByDesc('id')->first();
        $lastCallback = $order->callbacks->sortByDesc('id')->first();

        return [
            'merchant_name' => $settings->merchantName(),
            'merchant_id' => $settings->merchantId(),
            'terminal_id' => $settings->terminalId(),
            'payment_method' => $charge?->card_brand ?? 'BANK_CARD',
            'card_last4' => $charge?->card_last4,
            'approval_code' => $charge?->approval_code,
            'rrn' => $charge?->bank_transaction_id,
            'bank_transaction_id' => $charge?->bank_transaction_id,
            'bank_order_id' => $order->bank_order_id,
            'idempotency_key' => $order->bank_idempotency_key,
            'webhook_status' => $lastCallback !== null ? $lastCallback->bank_status.' / '.$lastCallback->outcome->value : null,
            'last_sync_at' => optional($order->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Order $order): array
    {
        $e = [];
        $e[] = $this->entry($order->created_at, 'order_created', 'Sifariş yaradıldı', $order->reference, 'customer', 'app', 'created', 'blue');

        if ($order->bank_order_id !== null) {
            $e[] = $this->entry($order->created_at, 'checkout_opened', 'Checkout açıldı', $order->bank_order_id, 'system', 'birpay', 'authorising', 'indigo');
        }
        foreach ($order->callbacks as $cb) {
            $e[] = $this->entry($cb->created_at, 'webhook_received', 'Webhook alındı', $cb->bank_status, 'bank', 'webhook', $cb->outcome->value, 'purple');
        }
        foreach ($order->payments as $p) {
            $isRefund = $p->type === PaymentType::Refund;
            $e[] = $this->entry(
                $p->occurred_at, $isRefund ? 'payment_refund' : 'payment_charge',
                $isRefund ? 'Geri qaytarma əməliyyatı' : 'Ödəniş təsdiqləndi',
                $p->bank_transaction_id, 'bank', 'birpay', $p->status->value, $isRefund ? 'amber' : 'green',
            );
        }
        if ($order->paid_at !== null) {
            $e[] = $this->entry($order->paid_at, 'paid', 'Ödəniş tamamlandı', null, 'system', 'birpay', 'paid', 'green');
        }
        if ($order->failed_at !== null) {
            $e[] = $this->entry($order->failed_at, $order->status->value, 'Sifariş bağlandı', $order->failed_reason, 'system', 'birpay', $order->status->value, 'red');
        }
        foreach ($order->refunds as $r) {
            $e[] = $this->entry($r->created_at, 'refund_requested', 'Geri qaytarma sorğusu', number_format($r->amount_minor / 100, 2), 'admin', 'admin', $r->status->value, 'amber');
            if ($r->completed_at !== null) {
                $e[] = $this->entry($r->completed_at, 'refund_completed', 'Geri qaytarma tamamlandı', $r->bank_refund_id, 'system', 'birpay', 'approved', 'green');
            } elseif ($r->status->value === 'failed') {
                $e[] = $this->entry($r->created_at, 'refund_failed', 'Geri qaytarma uğursuz', $r->error_message, 'system', 'birpay', 'failed', 'red');
            }
        }

        usort($e, static fn (array $a, array $b): int => (string) $a['at'] <=> (string) $b['at']);

        return $e;
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(mixed $at, string $event, string $description, ?string $detail, string $actor, string $source, string $status, string $color): array
    {
        return [
            'at' => optional($at)->toIso8601String(),
            'event' => $event,
            'description' => $description,
            'detail' => $detail,
            'actor' => $actor,
            'source' => $source,
            'status' => $status,
            'color' => $color,
        ];
    }
}
