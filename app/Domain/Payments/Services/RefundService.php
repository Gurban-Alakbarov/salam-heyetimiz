<?php

namespace App\Domain\Payments\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\RefundRequestData;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Events\OrderPartiallyRefunded;
use App\Domain\Payments\Events\OrderRefundRequested;
use App\Domain\Payments\Events\OrderRefunded;
use App\Domain\Payments\Exceptions\OrderNotRefundableException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Payments\Support\PaymentSettings;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Refund workflow (R-PAY-08/09). `request` validates eligibility and records intent; the financial
 * execution runs in ApplyRefundJob → `executeApprovedRefund` (bank call + payments row + order
 * status). The SUBSCRIPTION impact (§14.5.1 pro-rata) is NOT applied here — it is the Subscriptions
 * module's reaction to OrderRefunded / OrderPartiallyRefunded (batch 06, R-ARCH-06/07).
 */
final class RefundService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly PaymentGateway $gateway,
        private readonly PaymentSettings $paymentSettings,
        private readonly AuditLogger $audit,
    ) {}

    public function request(Order $order, RefundRequestData $data, AdminUser $admin): Refund
    {
        $this->assertRefundable($order, $data->amountMinor);

        $refund = $order->refunds()->create([
            'amount_minor' => $data->amountMinor,
            'reason' => $data->reason,
            'status' => RefundStatus::Requested,
            'requested_by_admin_id' => $admin->getKey(),
        ]);

        OrderRefundRequested::dispatch($refund);

        return $refund;
    }

    public function executeApprovedRefund(Refund $refund): void
    {
        $order = $refund->order;

        try {
            $this->assertRefundable($order, $refund->amount_minor);
        } catch (OrderNotRefundableException $e) {
            $this->markFailed($refund, $e->getMessage());

            return;
        }

        $netBefore = $order->netCapturedMinor();
        $refund->forceFill(['status' => RefundStatus::Processing->value])->save();

        $result = $this->gateway->refund(
            (string) $order->bank_order_id,
            $refund->amount_minor,
            'refund-'.$refund->id,
        );

        if (! $result->approved) {
            $this->markFailed($refund, $result->failureReason ?? 'refund_declined');

            return;
        }

        DB::transaction(function () use ($order, $refund, $result, $netBefore): void {
            $payment = $order->payments()->create([
                'bank_transaction_id' => $result->bankTransactionId,
                'type' => PaymentType::Refund,
                'amount_minor' => -1 * $refund->amount_minor,
                'currency' => $order->currency,
                'status' => PaymentStatus::Approved,
                'raw_response_encrypted' => json_encode(PaymentLogger::allowlist($result->raw), JSON_UNESCAPED_SLASHES) ?: null,
                'occurred_at' => $this->clock->now(),
            ]);

            $fullyRefunded = ($netBefore - $refund->amount_minor) <= 0;

            $order->forceFill([
                'status' => ($fullyRefunded ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded)->value,
            ])->save();

            $refund->forceFill([
                'status' => RefundStatus::Approved->value,
                'processed_by_admin_id' => $refund->requested_by_admin_id,
                'linked_payment_id' => $payment->id,
                'bank_refund_id' => $result->bankTransactionId,
                'completed_at' => $this->clock->now(),
                'error_message' => null,
            ])->save();

            if ($fullyRefunded) {
                OrderRefunded::dispatch($order, $refund);
            } else {
                OrderPartiallyRefunded::dispatch($order, $refund);
            }
        });

        $this->audit->record('refund.completed', [
            'refund_id' => (int) $refund->id,
            'order_id' => (int) $order->id,
            'bank_order_id' => $order->bank_order_id,
            'bank_refund_id' => $result->bankTransactionId,
            'amount_minor' => (int) $refund->amount_minor,
            'currency' => $order->currency,
            'bank_response' => $result->raw,
        ]);
    }

    public function markFailed(Refund $refund, string $reason): void
    {
        $refund->forceFill([
            'status' => RefundStatus::Failed->value,
            'error_message' => mb_substr($reason, 0, 255),
        ])->save();

        $this->audit->record('refund.failed', [
            'refund_id' => (int) $refund->id,
            'order_id' => (int) $refund->order_id,
            'amount_minor' => (int) $refund->amount_minor,
            'reason' => mb_substr($reason, 0, 255),
        ]);
    }

    private function assertRefundable(Order $order, int $amountMinor): void
    {
        if (! $order->status->isPaidLike()) {
            throw new OrderNotRefundableException(
                'Order is not in a refundable state.',
                ['status' => $order->status->value],
            );
        }

        $net = $order->netCapturedMinor();
        if ($amountMinor > $net) {
            throw new OrderNotRefundableException(
                'Refund amount exceeds the net captured amount.',
                ['net_minor' => $net, 'requested_minor' => $amountMinor],
            );
        }

        $windowDays = $this->paymentSettings->refundWindowDays();
        if ($order->paid_at !== null && $this->clock->now()->greaterThan($order->paid_at->copy()->addDays($windowDays))) {
            throw new OrderNotRefundableException(
                'The refund window has elapsed.',
                ['paid_at' => $order->paid_at->toIso8601String(), 'window_days' => $windowDays],
            );
        }
    }
}
