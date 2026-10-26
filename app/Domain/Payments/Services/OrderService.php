<?php

namespace App\Domain\Payments\Services;

use App\Domain\Audit\Services\AuditLogger;
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
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\SubscriptionDeliverability;
use App\Domain\Subscriptions\Support\SubscriptionPaymentAuthorizer;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        private readonly SubscriptionPaymentAuthorizer $payers,
        private readonly SubscriptionDeliverability $deliverability,
        private readonly AuditLogger $audit,
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
        $this->assertPayerMayPaySubscriptions($payer, $data->items); // 403 for someone else's revoked access
        $this->assertSubscriptionsDeliverable($data->items);          // 422 even for the beneficiary (K3)

        // MED-02 / R-PAY-11: never open a second in-flight order for the same subject.
        foreach ($data->items as $item) {
            if ($item->referencedId !== null) {
                $inFlight = $this->findInFlightForSubject($item->itemType, $item->referencedId);
                if ($inFlight !== null) {
                    // B7: another payer's open checkout is never handed out (payer ≠ beneficiary is allowed,
                    // but each order — and its checkout URL — belongs to whoever started it).
                    if ((int) $inFlight->payer_user_id !== (int) $payer->getKey()) {
                        throw ValidationException::withMessages(['items' => 'Bu abunəlik üçün ödəniş artıq başlanıb.']);
                    }

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

    /**
     * @param  bool  $late  K2 — the bank captured an order we had already expired on timeout (the user finished
     *                      on the bank page after our TTL): it is recorded as paid, never lost.
     */
    public function markPaid(Order $order, GatewayStatusResult $status, bool $late = false): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $order;
        }

        return DB::transaction(function () use ($order, $status, $late): Order {
            // A callback and a reconcile pass can settle the same order at once: the row lock makes the
            // capture (payment row + OrderPaid -> activation) happen exactly once.
            $current = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($current->status === OrderStatus::Paid) {
                return $order->refresh();
            }
            $previous = $current->status;

            $order->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => $this->clock->now(),
                'failed_reason' => null,
            ] + ($late ? [
                'failed_at' => null,
                'metadata' => array_merge($current->metadata ?? [], [
                    'late_capture' => ['previous_status' => $previous->value, 'captured_at' => $this->clock->now()->toIso8601String()],
                ]),
            ] : []))->save();

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

            if ($late) {
                $this->audit->record('order.late_capture', [
                    'order_id' => (int) $order->id,
                    'order_reference' => $order->reference,
                    'bank_order_id' => $order->bank_order_id,
                    'previous_status' => $previous->value,
                    'amount_minor' => (int) $order->amount_minor,
                ], Order::class, (int) $order->id);
                Log::warning('payments.late_capture', ['order_id' => (int) $order->id, 'reference' => $order->reference]);
            }

            OrderPaid::dispatch($order);

            return $order;
        });
    }

    /**
     * K3 — a paid line could not be delivered (its access is gone). Decision 2026-10-26: no automatic refund;
     * the order is flagged in `metadata.refund_required` (admin orders list `needs_refund=1`) so finance
     * refunds it through the normal refund flow. Repeated flags for the same order accumulate their lines.
     */
    public function flagRefundRequired(Order $order, int $subscriptionId, int $amountMinor, string $reason): void
    {
        DB::transaction(function () use ($order, $subscriptionId, $amountMinor, $reason): void {
            $current = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $metadata = $current->metadata ?? [];
            $flag = $metadata['refund_required'] ?? ['amount_minor' => 0, 'lines' => []];

            foreach ($flag['lines'] as $line) {
                if ((int) $line['subscription_id'] === $subscriptionId) {
                    return; // already flagged (listener replay)
                }
            }

            $flag['lines'][] = ['subscription_id' => $subscriptionId, 'amount_minor' => $amountMinor, 'reason' => $reason];
            $flag['amount_minor'] = (int) $flag['amount_minor'] + $amountMinor;
            $flag['flagged_at'] = $this->clock->now()->toIso8601String();
            $metadata['refund_required'] = $flag;

            $current->forceFill(['metadata' => $metadata])->save();
            $order->forceFill(['metadata' => $metadata])->syncOriginalAttribute('metadata');

            $this->audit->record('order.refund_required', [
                'order_id' => (int) $current->id,
                'order_reference' => $current->reference,
                'subscription_id' => $subscriptionId,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
            ], Order::class, (int) $current->id);
        });

        Log::warning('payments.refund_required', ['order_id' => (int) $order->id, 'subscription_id' => $subscriptionId, 'reason' => $reason]);
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
            $unit = $this->pricing->unitPriceFor($item);

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

    /**
     * K3 — a subscription whose access is gone (roster row revoked, device removed) is never offered for
     * payment, whoever pays: the money would buy nothing.
     *
     * @param  array<int, OrderItemData>  $items
     */
    private function assertSubscriptionsDeliverable(array $items): void
    {
        foreach ($items as $item) {
            if ($item->itemType === OrderItemType::Device || $item->referencedId === null) {
                continue;
            }

            $subscription = Subscription::query()->find($item->referencedId);
            if ($subscription !== null && $this->deliverability->blocker($subscription) !== null) {
                throw ValidationException::withMessages(['items' => __('errors.subscription_access_removed')]);
            }
        }
    }

    /**
     * B7 payer rule (SubscriptionPaymentAuthorizer): subscription lines may only be paid by the beneficiary,
     * their family head, or the private device owner. Device-sale lines are untouched (BR-20).
     *
     * @param  array<int, OrderItemData>  $items
     */
    private function assertPayerMayPaySubscriptions(User $payer, array $items): void
    {
        foreach ($items as $item) {
            if ($item->itemType === OrderItemType::Device || $item->referencedId === null) {
                continue;
            }

            $subscription = Subscription::query()->find($item->referencedId);
            if ($subscription !== null) {
                $this->payers->assertCanPay($payer, $subscription);
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
