<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;

/**
 * Applies the bank's AUTHORITATIVE status to an order. Per R-PAY-04 this ALWAYS calls
 * getOrderStatus and applies the bank's response — never the callback body — which is what makes a
 * forged or stale callback harmless. This is the non-negotiable defence-in-depth check.
 */
final class PaymentVerifierService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderService $orders,
    ) {}

    /** Returns true if a terminal state was applied; false if still PENDING (caller should recheck). */
    public function verifyAndApply(Order $order): bool
    {
        if ($order->status->isTerminal()) {
            return true; // already settled; idempotent no-op
        }

        if ($order->bank_order_id === null) {
            return false; // never registered with the bank; nothing to verify
        }

        $status = $this->gateway->getOrderStatus($order->bank_order_id);

        return $this->apply($order, $status);
    }

    public function verifyAndApplyByBankOrderId(string $bankOrderId): bool
    {
        $order = Order::query()->where('bank_order_id', $bankOrderId)->first();

        if ($order === null) {
            return false; // unmatched — nothing to apply
        }

        return $this->verifyAndApply($order);
    }

    private function apply(Order $order, GatewayStatusResult $status): bool
    {
        switch ($status->status) {
            case BankStatus::Approved:
                $this->orders->markPaid($order, $status);

                return true;
            case BankStatus::Declined:
                $this->orders->markFailed($order, 'declined', $status);

                return true;
            case BankStatus::Canceled:
                $this->orders->markCancelled($order);

                return true;
            case BankStatus::Refunded:
                $this->settleAsRefunded($order);

                return true;
            case BankStatus::Pending:
            default:
                return false;
        }
    }

    private function settleAsRefunded(Order $order): void
    {
        // A REFUNDED status arriving here (rather than via the admin refund flow) reconciles a paid
        // order to refunded; the financial detail is owned by the refund workflow.
        if (in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded], true)) {
            $order->forceFill(['status' => OrderStatus::Refunded->value])->save();
        }
    }
}
