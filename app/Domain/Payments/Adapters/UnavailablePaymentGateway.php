<?php

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;

/**
 * Bound when the fake gateway is requested (PAYMENT_GATEWAY=fake) but not permitted by its feature flags.
 * Every call fails as "provider unavailable" (the existing 503 path) — a misconfiguration can never reach
 * the real bank or silently simulate payments.
 */
final class UnavailablePaymentGateway implements PaymentGateway
{
    public function registerOrder(Order $order): GatewayRegisterResult
    {
        throw $this->unavailable();
    }

    public function getOrderStatus(string $bankOrderId): GatewayStatusResult
    {
        throw $this->unavailable();
    }

    public function refund(string $bankOrderId, int $amountMinor, string $idempotencyKey): GatewayRefundResult
    {
        throw $this->unavailable();
    }

    public function cancel(string $bankOrderId): bool
    {
        throw $this->unavailable();
    }

    private function unavailable(): PaymentProviderUnavailableException
    {
        return new PaymentProviderUnavailableException('Fake payment gateway requested but not enabled (payments.fake_enabled / allow_fake_in_production).');
    }
}
