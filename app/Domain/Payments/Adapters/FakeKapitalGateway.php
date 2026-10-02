<?php

namespace App\Domain\Payments\Adapters;

use App\Domain\Payments\DTOs\GatewayRefundResult;
use App\Domain\Payments\DTOs\GatewayRegisterResult;
use App\Domain\Payments\DTOs\GatewayStatusResult;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Fake PaymentGateway (R-ARCH-08 / IMPLEMENTATION_PLAN B1). Two uses, one class:
 *
 *  - **Test double** (testing env): tests drive the bank's authoritative responses via willReturnStatus()
 *    etc. Default status APPROVED (unchanged contract).
 *  - **Simulated hosted checkout** (PAYMENT_GATEWAY=fake + feature flags): registerOrder returns a signed
 *    "TEST ÖDƏNİŞ" checkout URL instead of the bank page; the customer's choice there is persisted via
 *    simulate() and read back by getOrderStatus — so the order → callback → getOrderStatus → return
 *    pipeline runs exactly as with BirPay. Default status PENDING (nothing is paid until simulated).
 *
 * Never performs a network request. Fake bank order ids carry BANK_ORDER_PREFIX so orders can be
 * recognised as test payments (OrderResource.is_test, return-page banner).
 */
final class FakeKapitalGateway implements PaymentGateway
{
    public const BANK_ORDER_PREFIX = 'KB-FAKE-';

    private const STATE_KEY = 'payments:fake:state:';

    private const STATE_TTL_DAYS = 14;

    /** @var array<int, array{bankOrderId:string, amount_minor:int}> */
    public array $registered = [];

    /** @var array<int, array{bankOrderId:string, amount_minor:int, idempotencyKey:string}> */
    public array $refunds = [];

    private BankStatus $nextStatus;

    private ?int $statusAmountMinor = null;

    private bool $refundApproved = true;

    private bool $unavailable = false;

    public function __construct(BankStatus $defaultStatus = BankStatus::Approved)
    {
        $this->nextStatus = $defaultStatus;
    }

    public static function isFakeBankOrderId(?string $bankOrderId): bool
    {
        return is_string($bankOrderId) && str_starts_with($bankOrderId, self::BANK_ORDER_PREFIX);
    }

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

    /** Persist the simulated bank outcome for a fake order (survives across HTTP requests). */
    public function simulate(string $bankOrderId, BankStatus $status, ?int $amountMinor): void
    {
        Cache::put(self::STATE_KEY.$bankOrderId, [
            'status' => $status->value,
            'amount_minor' => $amountMinor,
        ], now()->addDays(self::STATE_TTL_DAYS));
    }

    public function simulatedStatus(string $bankOrderId): ?BankStatus
    {
        $state = Cache::get(self::STATE_KEY.$bankOrderId);

        return is_array($state) ? BankStatus::tryFromString((string) ($state['status'] ?? '')) : null;
    }

    public function registerOrder(Order $order): GatewayRegisterResult
    {
        $this->guardAvailable();
        // Unique per order (the gateway is re-instantiated per request at runtime, so no counters).
        $bankOrderId = self::BANK_ORDER_PREFIX.Str::upper((string) Str::ulid());
        $this->registered[] = ['bankOrderId' => $bankOrderId, 'amount_minor' => (int) $order->amount_minor];

        return new GatewayRegisterResult(
            bankOrderId: $bankOrderId,
            redirectUrl: $this->checkoutUrl((string) $order->reference),
            raw: ['orderId' => $bankOrderId, 'status' => 'PENDING', 'test' => true],
        );
    }

    public function getOrderStatus(string $bankOrderId): GatewayStatusResult
    {
        $this->guardAvailable();

        $state = Cache::get(self::STATE_KEY.$bankOrderId);
        $status = is_array($state)
            ? (BankStatus::tryFromString((string) ($state['status'] ?? '')) ?? $this->nextStatus)
            : $this->nextStatus;
        $amount = is_array($state) ? ($state['amount_minor'] ?? null) : $this->statusAmountMinor;

        return new GatewayStatusResult(
            status: $status,
            amountMinor: $amount !== null ? (int) $amount : null,
            bankTransactionId: 'TXN-'.$bankOrderId,
            panMasked: '**** **** **** 1234',
            cardBrand: 'VISA',
            // Short, deterministic codes — payments.approval_code is VARCHAR(20) (bank-sized).
            rrn: 'RRN'.strtoupper(substr(md5($bankOrderId), 0, 9)),
            approvalCode: 'APR'.strtoupper(substr(md5($bankOrderId), 0, 6)),
            raw: ['orderId' => $bankOrderId, 'status' => $status->value, 'test' => true],
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
            raw: ['status' => $this->refundApproved ? 'REFUNDED' : 'DECLINED', 'test' => true],
        );
    }

    public function cancel(string $bankOrderId): bool
    {
        $this->guardAvailable();

        return true;
    }

    /** Signed (relative-signature) link to the simulated hosted page; absolute for the app to open. */
    private function checkoutUrl(string $reference): string
    {
        $ttl = (int) config('domain.payments.fake_checkout_ttl_minutes', 30);

        return url(URL::temporarySignedRoute('fakeCheckout', now()->addMinutes($ttl), ['reference' => $reference], false));
    }

    private function guardAvailable(): void
    {
        if ($this->unavailable) {
            throw new PaymentProviderUnavailableException('Fake gateway marked unavailable.');
        }
    }
}
