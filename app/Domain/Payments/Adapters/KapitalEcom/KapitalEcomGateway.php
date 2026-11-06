<?php

namespace App\Domain\Payments\Adapters\KapitalEcom;

use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Exceptions\KapitalEcomApiException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Support\PaymentSettings;

/**
 * Kapital e-commerce implementation of the PaymentGateway boundary. A customer pays on the bank's hosted page; the
 * bank only redirects the browser back (there is no webhook), so an order is settled by reading it with
 * getOrderStatus — from the return page, or from the reconciler when the customer never comes back.
 */
final class KapitalEcomGateway implements PaymentGateway
{
    private const TITLE = 'Salam Sesame';

    private const LANGUAGES = ['az', 'en', 'ru'];

    public function __construct(
        private readonly KapitalEcomClient $client,
        private readonly PaymentSettings $settings,
    ) {}

    public function registerOrder(Order $order): GatewayRegisterResult
    {
        $locale = app()->getLocale();

        $created = $this->client->createOrder(
            amountMinor: (int) $order->amount_minor,
            currency: $order->currency ?: $this->settings->currency(),
            title: self::TITLE,
            description: 'Order '.$order->reference,
            language: in_array($locale, self::LANGUAGES, true) ? $locale : self::LANGUAGES[0],
            redirectUrl: $order->return_url ?: $this->settings->returnUrl(),
            orderId: (int) $order->id,
        );

        return new GatewayRegisterResult(
            (string) $created['id'],
            self::hostedPageUrl($created),
            ['id' => (string) $created['id'], 'status' => $created['status'] ?? null],
        );
    }

    /**
     * The order `password` opens the hosted page; the response's hppUrl already ends in /flex.
     *
     * @param  array<string, mixed>  $created
     */
    public static function hostedPageUrl(array $created): string
    {
        return $created['hppUrl'].'?id='.rawurlencode((string) $created['id']).'&password='.rawurlencode((string) $created['password']);
    }

    public function getOrderStatus(string $bankOrderId): GatewayStatusResult
    {
        $order = $this->client->getOrder($bankOrderId);
        $purchase = $this->lastTran($order, 'Purchase');
        $rrn = $this->rrn($purchase);
        $brand = $order['srcToken']['card']['brand'] ?? null;

        return new GatewayStatusResult(
            status: self::mapStatus($order),
            amountMinor: KapitalEcomClient::minor($order['amount'] ?? 0),
            bankTransactionId: $rrn ?? (isset($purchase['actionId']) ? (string) $purchase['actionId'] : null),
            panMasked: isset($order['srcToken']['displayName']) ? (string) $order['srcToken']['displayName'] : null,
            cardBrand: is_string($brand) ? $brand : null,
            rrn: $rrn,
            approvalCode: isset($purchase['approvalCode']) ? (string) $purchase['approvalCode'] : null,
            raw: array_filter([
                'id' => $bankOrderId,
                'status' => $order['status'],
                'amount' => $order['amount'] ?? null,
                'currency' => $order['currency'] ?? null,
                'rrn' => $rrn,
                'approvalCode' => $purchase['approvalCode'] ?? null,
                'cardBrand' => is_string($brand) ? $brand : null,
            ], static fn (mixed $v): bool => $v !== null),
        );
    }

    /**
     * The API has no idempotency key, so a refund that was sent but lost its answer must not be sent again. What
     * the bank has refunded beyond the refunds we recorded is that lost refund: it is reported as done instead.
     */
    public function refund(string $bankOrderId, int $amountMinor, string $idempotencyKey): GatewayRefundResult
    {
        $current = Order::query()->where('bank_order_id', $bankOrderId)->first();
        $orderId = $current !== null ? (int) $current->id : null;
        $recordedMinor = $current !== null
            ? abs((int) $current->payments()->where('status', 'approved')->where('type', 'refund')->sum('amount_minor'))
            : 0;

        $bankOrder = $this->client->getOrder($bankOrderId, $orderId);
        if (KapitalEcomClient::minor($bankOrder['clearedRefundAmount'] ?? 0) - $recordedMinor >= $amountMinor) {
            return new GatewayRefundResult(
                approved: true,
                bankTransactionId: $this->rrn($this->lastTran($bankOrder, 'Refund')),
                raw: ['id' => $bankOrderId, 'status' => 'already_refunded'],
            );
        }

        try {
            $tran = $this->client->refund($bankOrderId, $amountMinor, $orderId);
        } catch (KapitalEcomApiException $e) {
            if (! $e->isDefinite()) {
                throw $e; // outcome unknown → the job retries, and the check above decides
            }

            return new GatewayRefundResult(
                approved: false,
                failureReason: mb_substr($e->bankCode().': '.$e->getMessage(), 0, 200),
                raw: ['code' => $e->bankCode(), 'message' => $e->getMessage()],
            );
        }

        $approved = (string) ($tran['pmoResultCode'] ?? '') === '1';
        $rid = (string) ($tran['match']['ridByPmo'] ?? $tran['match']['tranActionId'] ?? '');
        if ($approved) {
            $rid = $this->refundRrn($bankOrderId, $orderId) ?? $rid;
        }

        return new GatewayRefundResult(
            approved: $approved,
            bankTransactionId: $rid !== '' ? $rid : null,
            failureReason: $approved ? null : 'refund_pmo_'.(string) ($tran['pmoResultCode'] ?? 'unknown'),
            raw: array_filter(['id' => $bankOrderId, 'approvalCode' => $tran['approvalCode'] ?? null, 'rrn' => $rid !== '' ? $rid : null]),
        );
    }

    /** The API cannot cancel an unpaid order: it simply expires at the bank. */
    public function cancel(string $bankOrderId): bool
    {
        return false;
    }

    /**
     * Map the bank's order status onto BankStatus. Anything unknown stays Pending: the order is asked again and
     * never settled on a guess. `Closed` and `PartPaid` (the bank's status after a partial refund) depend on the
     * money still captured.
     *
     * @param  array<string, mixed>  $order
     */
    public static function mapStatus(array $order): BankStatus
    {
        return match (strtolower(str_replace(' ', '', (string) ($order['status'] ?? '')))) {
            'fullypaid' => BankStatus::Approved,
            'declined', 'rejected' => BankStatus::Declined,
            'cancelled', 'refused', 'expired' => BankStatus::Canceled,
            'refunded', 'voided' => BankStatus::Refunded,
            'partpaid', 'partiallypaid' => self::moneyRemains($order) ? BankStatus::Approved : BankStatus::Pending,
            'closed' => self::moneyRemains($order) ? BankStatus::Approved : BankStatus::Refunded,
            default => BankStatus::Pending,
        };
    }

    /** @param  array<string, mixed>  $order */
    private static function moneyRemains(array $order): bool
    {
        return KapitalEcomClient::minor($order['clearedChargeAmount'] ?? 0) > KapitalEcomClient::minor($order['clearedRefundAmount'] ?? 0);
    }

    /** The refund's answer has no RRN; only the order shows it. A failed read must not undo a done refund. */
    private function refundRrn(string $bankOrderId, ?int $orderId): ?string
    {
        try {
            return $this->rrn($this->lastTran($this->client->getOrder($bankOrderId, $orderId), 'Refund'));
        } catch (KapitalEcomApiException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function lastTran(array $order, string $type): array
    {
        $trans = array_filter(
            is_array($order['trans'] ?? null) ? $order['trans'] : [],
            static fn (mixed $t): bool => is_array($t) && ($t['type'] ?? null) === $type,
        );

        return $trans === [] ? [] : end($trans);
    }

    /** @param  array<string, mixed>  $tran */
    private function rrn(array $tran): ?string
    {
        $rrn = $tran['rrn'] ?? $tran['ridByPmo'] ?? null;

        return $rrn !== null ? (string) $rrn : null;
    }
}
