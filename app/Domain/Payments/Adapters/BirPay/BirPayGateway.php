<?php

namespace App\Domain\Payments\Adapters\BirPay;

use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\BirPay\CreatePaymentData;
use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Exceptions\BirPayCapabilityNotEnabledException;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\PaymentLogger;
use App\Domain\Payments\Support\PaymentSettings;
use Illuminate\Support\Str;

/**
 * BirPay implementation of the PaymentGateway boundary. Phase 2 implements registerOrder (create payment) +
 * getOrderStatus (retrieve payment, mapped to the existing BankStatus). refund/cancel are an explicit
 * capability boundary delivered in a later phase (IMPLEMENTATION_ORDER) — they refuse honestly, never fake.
 */
final class BirPayGateway implements PaymentGateway
{
    public function __construct(
        private readonly BirPayClient $client,
        private readonly PaymentSettings $settings,
    ) {}

    public function registerOrder(Order $order): GatewayRegisterResult
    {
        $idempotencyKey = $order->bank_idempotency_key ?: (string) Str::uuid();

        $payment = $this->client->createPayment(
            new CreatePaymentData(
                amountMinor: (int) $order->amount_minor,
                currency: $order->currency ?: $this->settings->currency(),
                capture: true,
                description: 'Order '.$order->reference,
                paymentMethodType: '', // omit → hosted REDIRECT page offers all methods (incl. card)
                confirmationType: 'REDIRECT',
                returnUrl: $order->return_url ?: $this->settings->returnUrl(),
                metadata: ['orderNo' => $order->reference],
                posDetail: $this->settings->posDetail(),
            ),
            $idempotencyKey,
            (int) $order->id,
        );

        if (($payment->confirmUrl ?? '') === '') {
            throw new PaymentProviderUnavailableException('BirPay createPayment: no confirmUrl in response.');
        }

        return new GatewayRegisterResult($payment->id, (string) $payment->confirmUrl, $payment->raw);
    }

    public function getOrderStatus(string $bankOrderId): GatewayStatusResult
    {
        $payment = $this->client->getPayment($bankOrderId);

        return new GatewayStatusResult(
            status: $this->mapStatus($payment->status, $payment->cancelationReason),
            amountMinor: $payment->amountMinor,
            bankTransactionId: $payment->rrn,
            panMasked: null,
            cardBrand: null,
            rrn: $payment->rrn,
            approvalCode: $payment->approvalCode,
            raw: $payment->raw,
        );
    }

    public function refund(string $bankOrderId, int $amountMinor, string $idempotencyKey): GatewayRefundResult
    {
        // POST /v1/refunds {id: payment id, amount, description}. No confirmation → refunded directly. The
        // same idempotency key (refund-<id>) makes retries safe. Direct card refunds settle to `succeeded`;
        // if still `pending` we poll the refund a few times before deciding.
        $refund = $this->client->createRefund($bankOrderId, $amountMinor, 'Refund '.$bankOrderId, $idempotencyKey);
        $refundId = (string) ($refund['id'] ?? '');
        $status = strtolower((string) ($refund['status'] ?? ''));

        $tries = 0;
        while ($status === 'pending' && $refundId !== '' && $tries < 5) {
            if (! app()->environment('testing')) {
                usleep(500_000);
            }
            $tries++;
            $refund = $this->client->getRefund($refundId);
            $status = strtolower((string) ($refund['status'] ?? ''));
        }

        // `canceled` (or no id) = genuine failure; `succeeded`/`pending` = accepted (money is being returned).
        $approved = $refundId !== '' && $status !== 'canceled';

        return new GatewayRefundResult(
            approved: $approved,
            bankTransactionId: $refundId !== '' ? $refundId : null,
            failureReason: $approved ? null : ('refund_'.($status !== '' ? $status : 'rejected')),
            raw: PaymentLogger::allowlist($refund),
        );
    }

    public function cancel(string $bankOrderId): bool
    {
        // Pre-capture cancellation (PUT /v1/payments/{id}/cancel) is delivered in a later phase.
        throw new BirPayCapabilityNotEnabledException('BirPay cancel is delivered in a later phase.');
    }

    /** Map BirPay payment status (+ cancelation reason) onto the existing BankStatus enum. */
    private function mapStatus(string $status, ?string $reason): BankStatus
    {
        return match ($status) {
            'succeeded' => BankStatus::Approved,
            'pending', 'waiting_for_capture' => BankStatus::Pending,
            'canceled' => in_array(strtolower((string) $reason), ['canceled_by_merchant', 'canceled_by_payment_network'], true)
                ? BankStatus::Canceled
                : BankStatus::Declined,
            default => BankStatus::Pending,
        };
    }
}
