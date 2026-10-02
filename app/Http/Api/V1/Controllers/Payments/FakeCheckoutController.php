<?php

namespace App\Http\Api\V1\Controllers\Payments;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\DTOs\KapitalCallbackData;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\PaymentCallbackService;
use App\Domain\Payments\Support\PaymentGatewayMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Simulated BirPay hosted payment page — "TEST ÖDƏNİŞ" (IMPLEMENTATION_PLAN B1 / BR-16). Reachable only
 * through a signed link minted by FakeKapitalGateway::registerOrder and only while the fake gateway is
 * active (404 otherwise). The customer's choice is persisted as the simulated bank status and then fed
 * through the SAME callback pipeline a BirPay webhook uses (PaymentCallbackService::receive →
 * HandlePaymentCallbackReceived → getOrderStatus verify) before redirecting to the existing
 * /v1/payments/return page — so no business logic is fake-specific.
 */
class FakeCheckoutController
{
    private const ACTIONS = [
        'pay' => BankStatus::Approved,
        'decline' => BankStatus::Declined,
        'cancel' => BankStatus::Canceled,
        'pending' => BankStatus::Pending,
    ];

    /** GET /v1/payments/fake-checkout/{reference} (signed) — the simulated hosted page. */
    public function show(string $reference): Response
    {
        $order = $this->resolveOrder($reference);
        $ttl = (int) config('domain.payments.fake_checkout_ttl_minutes', 30);

        $actions = [];
        foreach (array_keys(self::ACTIONS) as $action) {
            $actions[$action] = URL::temporarySignedRoute(
                'fakeCheckoutAction',
                now()->addMinutes($ttl),
                ['reference' => $order->reference, 'action' => $action],
                false,
            );
        }

        return response()->view('payments.fake-checkout', [
            'order' => $order,
            'amount' => number_format(((int) $order->amount_minor) / 100, 2, '.', ' '),
            'settled' => $order->status->isTerminal(),
            'actions' => $actions,
            'returnUrl' => route('paymentReturn', ['paymentId' => $order->bank_order_id], false),
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }

    /** POST /v1/payments/fake-checkout/{reference}/{action} (signed) — simulate the bank outcome. */
    public function act(Request $request, string $reference, string $action, PaymentCallbackService $callbacks, AuditLogger $audit): RedirectResponse
    {
        $order = $this->resolveOrder($reference);
        $status = self::ACTIONS[$action] ?? null;
        abort_if($status === null, 404);

        $bankOrderId = (string) $order->bank_order_id;
        $returnUrl = route('paymentReturn', ['paymentId' => $bankOrderId]);

        if ($order->status->isTerminal()) {
            return redirect()->to($returnUrl); // already settled — idempotent, nothing to simulate
        }

        $this->gateway()->simulate($bankOrderId, $status, (int) $order->amount_minor);

        // Same shape + pipeline as the BirPay webhook (signature check is the only step a fake skips;
        // the authoritative getOrderStatus re-check still decides the order state — R-PAY-04).
        $raw = (string) json_encode(['event' => 'fake.checkout', 'payload' => ['id' => $bankOrderId, 'status' => $status->value]]);
        $callback = $callbacks->receive(
            KapitalCallbackData::fromArray(['orderId' => $bankOrderId, 'status' => $status->value, 'amount' => (string) $order->amount_minor]),
            $raw,
            $request->ip(),
        );

        $audit->record('payment.fake_checkout_action', [
            'order_reference' => $order->reference,
            'bank_order_id' => $bankOrderId,
            'action' => $action,
            'simulated_status' => $status->value,
            'callback_id' => (int) $callback->id,
        ], Order::class, (int) $order->id);

        return redirect()->to($returnUrl);
    }

    private function resolveOrder(string $reference): Order
    {
        abort_unless(PaymentGatewayMode::fakeActive(), 404);
        abort_unless(app(PaymentGateway::class) instanceof FakeKapitalGateway, 404);

        $order = Order::query()->where('reference', $reference)->first();
        abort_if($order === null || ! FakeKapitalGateway::isFakeBankOrderId($order->bank_order_id), 404);

        return $order;
    }

    private function gateway(): FakeKapitalGateway
    {
        /** @var FakeKapitalGateway $gateway */
        $gateway = app(PaymentGateway::class);

        return $gateway;
    }
}
