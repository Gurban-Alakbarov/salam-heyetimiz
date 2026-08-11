<?php

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;

/**
 * In-memory PaymentGateway test double (R-ARCH-08 / BACKEND §13.1). Bound ONLY in the testing
 * environment by IntegrationsServiceProvider — it never serves production traffic. Lets tests
 * drive the bank's authoritative responses (e.g. APPROVED vs DECLINED) to exercise the
 * getOrderStatus defence-in-depth path.
 */
final class FakeKapitalGateway implements PaymentGateway
{
    /** @var array<int, array{bankOrderId:string, amount_minor:int}> */
    public array $registered = [];

    /** @var array<int, array{bankOrderId:string, amount_minor:int, idempotencyKey:string}> */
    public array $refunds = [];

    private BankStatus $nextStatus = BankStatus::Approved;

    private ?int $statusAmountMinor = null;

    private bool $refundApproved = true;

    private bool $unavailable = false;

    private int $counter = 0;

    public function willReturnStatus(BankStatus $status, ?int $amountMinor = null): void
    {
        $this->nextStatus = $status;
        $this->statusAmountMinor = $amountMinor;
    }

    public function willDeclineRefund(): void
    {
        $this->refundApproved = false;
    }

    public function makeUnavailable(): void
    {
        $this->unavailable = true;
    }

    public function registerOrder(Order $order): GatewayRegisterResult
    {
        $this->guardAvailable();
        $bankOrderId = 'KB-FAKE-'.(++$this->counter);
        $this->registered[] = ['bankOrderId' => $bankOrderId, 'amount_minor' => (int) $order->amount_minor];

        return new GatewayRegisterResult(
            bankOrderId: $bankOrderId,
            redirectUrl: 'https://e-commerce.kapitalbank.az/fake/'.$bankOrderId,
            raw: ['orderId' => $bankOrderId, 'status' => 'PENDING'],
        );
    }

    public function getOrderStatus(string $bankOrderId): GatewayStatusResult
    {
        $this->guardAvailable();

        return new GatewayStatusResult(
            status: $this->nextStatus,
            amountMinor: $this->statusAmountMinor,
            bankTransactionId: 'TXN-'.$bankOrderId,
            panMasked: '**** **** **** 1234',
            cardBrand: 'VISA',
            rrn: 'RRN'.$bankOrderId,
            approvalCode: 'APR'.$bankOrderId,
            raw: ['orderId' => $bankOrderId, 'status' => $this->nextStatus->value],
        );
    }

    public function refund(string $bankOrderId, int $amountMinor, string $idempotencyKey): GatewayRefundResult
    {
        $this->guardAvailable();
        $this->refunds[] = ['bankOrderId' => $bankOrderId, 'amount_minor' => $amountMinor, 'idempotencyKey' => $idempotencyKey];

        return new GatewayRefundResult(
            approved: $this->refundApproved,
            // unique per refund (mirrors the real BirPay refund id), keyed off the idempotency key
            bankTransactionId: $this->refundApproved ? 'RFND-'.$idempotencyKey : null,
            failureReason: $this->refundApproved ? null : 'refund_declined',
            raw: ['status' => $this->refundApproved ? 'REFUNDED' : 'DECLINED'],
        );
    }

    public function cancel(string $bankOrderId): bool
    {
        $this->guardAvailable();

        return true;
    }

    private function guardAvailable(): void
    {
        if ($this->unavailable) {
            throw new PaymentProviderUnavailableException('Fake gateway marked unavailable.');
        }
    }
}
