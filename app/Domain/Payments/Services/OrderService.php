<?php

namespace App\Domain\Payments\Services;

use App\Domain\Devices\Services\DeviceLookup;
use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\DTOs\OrderCreationData;
use App\Domain\Payments\DTOs\OrderItemData;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Events\OrderAuthorising;
use App\Domain\Payments\Events\OrderCancelled;
use App\Domain\Payments\Events\OrderCreated;
use App\Domain\Payments\Events\OrderExpired;
use App\Domain\Payments\Events\OrderFailed;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Support\OrderPricing;
use App\Domain\Payments\Support\PaymentSettings;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns the order aggregate: creation (with server-side pricing, idempotency, device-sale linkage)
 * and all status transitions. Transactions are opened here (R-ARCH-05). The bank HTTP call runs
 * outside the DB transaction per the Tech Spec §14.2 sequence (pending → register → authorising).
 */
final class OrderService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentGateway $gateway,
        private readonly OrderPricing $pricing,
        private readonly DeviceLookup $devices,
        private readonly PaymentSettings $paymentSettings,
    ) {}

    public function create(User $payer, OrderCreationData $data, ?string $idempotencyKey): Order
    {
        // Idempotent replay: same payer + key returns the original order (orders.idempotency_key).
        if ($idempotencyKey !== null) {
            $existing = Order::query()
                ->where('payer_user_id', $payer->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $lines = $this->priceItems($data->items);
        $this->assertDeviceSaleItemsAreSellable($data->items);

        // MED-02 / R-PAY-11: never open a second in-flight order for the same subject.
        foreach ($data->items as $item) {
            if ($item->referencedId !== null) {
                $inFlight = $this->findInFlightForSubject($item->itemType, $item->referencedId);
                if ($inFlight !== null) {
                    return $inFlight;
                }
            }
        }

        $amountMinor = array_sum(array_column($lines, 'total_amount_minor'));

        $order = DB::transaction(function () use ($payer, $data, $lines, $amountMinor, $idempotencyKey): Order {
            $order = new Order([
                'reference' => 'PENDING',
                'payer_user_id' => $payer->getKey(),
                'purpose' => $data->purpose,
                'amount_minor' => $amountMinor,
                'currency' => 'AZN',
                'status' => OrderStatus::Pending,
                'idempotency_key' => $idempotencyKey,
                'return_url' => $data->returnUrl,
                'expires_at' => $this->clock->now()->addMinutes($this->callbackTimeoutMinutes()),
            ]);
            $order->save();

            $order->reference = 'SH-'.$this->clock->now()->format('Ym').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            return $order;
        });

        OrderCreated::dispatch($order);

        // A stable per-order bank idempotency key, persisted before registration so create-payment retries
        // (after a 5xx) reuse the same X-Idempotency-Key and never double-charge (FINAL_IMPLEMENTATION_SPEC §11).
        if (empty($order->bank_idempotency_key)) {
            $order->forceFill(['bank_idempotency_key' => (string) Str::uuid()])->save();
        }

        // Register with the bank OUTSIDE the transaction (network call). On failure the order stays
        // `pending` and the controller returns 503; the expiry sweep reclaims stale pending orders.
        $result = $this->gateway->registerOrder($order);

        $order->forceFill([
            'bank_order_id' => $result->bankOrderId,
            'bank_redirect_url' => $result->redirectUrl,
            'status' => OrderStatus::Authorising,
        ])->save();

        OrderAuthorising::dispatch($order);

        return $order->refresh();
    }

    public function markPaid(Order $order, GatewayStatusResult $status): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order, $status): Order {
            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => $this->clock->now(),
                'failed_reason' => null,
            ])->save();

            $order->payments()->create([
                'bank_transaction_id' => $status->bankTransactionId,
                'type' => PaymentType::Charge,
                'amount_minor' => (int) $order->amount_minor,
                'currency' => $order->currency,
                'status' => PaymentStatus::Approved,
                'card_brand' => $status->cardBrand,
                'card_last4' => $this->last4($status->panMasked),
                'approval_code' => $status->approvalCode,
                'raw_response_encrypted' => $this->encodeRaw($status->raw),
                'occurred_at' => $this->clock->now(),
            ]);

            OrderPaid::dispatch($order);

            return $order;
        });
    }

    public function markFailed(Order $order, ?string $reason, GatewayStatusResult $status): Order
    {
        if ($order->status->isTerminal()) {
            return $order;
        }

        return DB::transaction(function () use ($order, $reason, $status): Order {
            $order->forceFill([
                'status' => OrderStatus::Failed,
                'failed_at' => $this->clock->now(),
                'failed_reason' => $reason,
            ])->save();

            $order->payments()->create([
                'bank_transaction_id' => $status->bankTransactionId,
                'type' => PaymentType::Charge,
                'amount_minor' => (int) $order->amount_minor,
                'currency' => $order->currency,
                'status' => PaymentStatus::Declined,
                'raw_response_encrypted' => $this->encodeRaw($status->raw),
                'occurred_at' => $this->clock->now(),
            ]);

            OrderFailed::dispatch($order, $reason);

            return $order;
        });
    }

    public function markCancelled(Order $order): Order
    {
        if ($order->status->isTerminal()) {
            return $order;
        }

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'failed_at' => $this->clock->now(),
        ])->save();

        OrderCancelled::dispatch($order);

        return $order;
    }

    /** Expire in-flight orders past their TTL (callback never arrived). Returns the count expired. */
    public function expireStale(): int
    {
        $cutoff = $this->clock->now();
        $count = 0;

        Order::query()
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Authorising->value])
            ->where('expires_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    $order->forceFill([
                        'status' => OrderStatus::Expired->value,
                        'failed_at' => $this->clock->now(),
                        'failed_reason' => 'callback_timeout',
                    ])->save();
                    OrderExpired::dispatch($order);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<int, OrderItemData>  $items
     * @return array<int, array{item_type:OrderItemType, referenced_id:?int, description:string, quantity:int, unit_amount_minor:int, total_amount_minor:int}>
     */
    private function priceItems(array $items): array
    {
        return array_map(function (OrderItemData $item): array {
            $unit = $this->pricing->unitPriceMinor($item->itemType);

            return [
                'item_type' => $item->itemType,
                'referenced_id' => $item->referencedId,
                'description' => $this->pricing->descriptionFor($item->itemType),
                'quantity' => $item->quantity,
                'unit_amount_minor' => $unit,
                'total_amount_minor' => $unit * $item->quantity,
            ];
        }, $items);
    }

    /**
     * @param  array<int, OrderItemData>  $items
     */
    private function assertDeviceSaleItemsAreSellable(array $items): void
    {
        foreach ($items as $item) {
            if ($item->itemType !== OrderItemType::Device) {
                continue;
            }

            if ($item->referencedId === null || ! $this->devices->isUnassigned($item->referencedId)) {
                throw ValidationException::withMessages([
                    'items' => __('errors.device_not_sellable'),
                ]);
            }
        }
    }

    private function findInFlightForSubject(OrderItemType $type, int $referencedId): ?Order
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Authorising->value])
            ->whereHas('items', function ($query) use ($type, $referencedId): void {
                $query->where('item_type', $type->value)->where('referenced_id', $referencedId);
            })
            ->orderByDesc('id')
            ->first();
    }

    private function callbackTimeoutMinutes(): int
    {
        return $this->paymentSettings->callbackTimeoutMinutes();
    }

    private function last4(?string $pan): ?string
    {
        if ($pan === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $pan) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function encodeRaw(array $raw): ?string
    {
        return json_encode(PaymentLogger::allowlist($raw), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
