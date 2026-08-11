<?php

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Models\Order;

/**
 * Payment provider boundary (R-ARCH-08; BACKEND §14.8). Default impl: BirPay\BirPayGateway
 * (BirPay/Kapital Checkout V1.3, hosted page). Test impl: FakeKapitalGateway. Transport failures throw
 * PaymentProviderUnavailableException; BirPay API errors throw BirPayApiException.
 */
interface PaymentGateway
{
    /** Register an order with the bank; returns the bank order id + hosted-page redirect URL. */
    public function registerOrder(Order $order): GatewayRegisterResult;

    /** Authoritative order status (R-PAY-04). Always consulted before mutating an order. */
    public function getOrderStatus(string $bankOrderId): GatewayStatusResult;

    /** Refund (full or partial) against a bank order; idempotency-keyed (R-PAY-09). */
    public function refund(string $bankOrderId, int $amountMinor, string $idempotencyKey): GatewayRefundResult;

    /** Cancel an order that has not been captured. */
    public function cancel(string $bankOrderId): bool;
}
