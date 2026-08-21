<?php

use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\PaymentCallback;
use App\Domain\Payments\Services\OrderReconciler;
use App\Domain\Payments\Services\PaymentVerifierService;

/*
| Phase 3 — BirPay webhook (X-Signature), payment-state sync (authoritative getOrderStatus), return URL,
| reconciliation. The bound PaymentGateway in testing is FakeKapitalGateway → drives getOrderStatus.
*/

beforeEach(function () {
    app(\App\Domain\Admin\Services\SettingsService::class)->setGroup('payments', ['kapital_webhook_secret' => 'wh-secret-123']);
});

function makeAuthorisingOrder(string $bankOrderId = 'bp-pay-1', int $amountMinor = 1000): Order
{
    return Order::query()->create([
        'reference' => 'SH-T-'.substr($bankOrderId, -6),
        'payer_user_id' => makeUser('+99450'.random_int(1000000, 9999999))->getKey(),
        'purpose' => 'sub_main',
        'amount_minor' => $amountMinor,
        'currency' => 'AZN',
        'status' => OrderStatus::Authorising,
        'bank_order_id' => $bankOrderId,
        'bank_idempotency_key' => 'idem-'.$bankOrderId,
        'expires_at' => now()->addMinutes(30),
    ]);
}

function postWebhook(string $event, string $bankOrderId, string $status, ?string $signature = null): \Illuminate\Testing\TestResponse
{
    $body = json_encode(['event' => $event, 'payload' => ['id' => $bankOrderId, 'type' => 'purchase', 'paymentMethod' => 'birbank', 'status' => $status]]);
    $sig = $signature ?? signBirPay($body);

    return test()->call('POST', '/v1/payments/webhook', [], [], [], ['HTTP_X_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $body);
}

it('accepts a validly-signed webhook and settles the order via authoritative getOrderStatus', function () {
    $order = makeAuthorisingOrder();
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);

    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded')->assertOk()->assertJsonPath('status', 'accepted');

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(PaymentCallback::query()->where('bank_order_id', $order->bank_order_id)->count())->toBe(1);
});

it('rejects a wrong signature with 401', function () {
    $order = makeAuthorisingOrder('bp-wrong');
    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded', signature: 'not-a-valid-signature')->assertStatus(401);
    expect($order->refresh()->status)->toBe(OrderStatus::Authorising);
});

it('rejects a missing signature with 401', function () {
    $order = makeAuthorisingOrder('bp-nosig');
    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded', signature: '')->assertStatus(401);
});

it('de-duplicates an identical webhook (idempotent, processed once)', function () {
    $order = makeAuthorisingOrder('bp-dup');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);

    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded')->assertOk();
    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded')->assertOk(); // same bytes → dedup

    expect(PaymentCallback::query()->where('bank_order_id', $order->bank_order_id)->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('records an unmatched webhook without touching any order', function () {
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, 1000);
    postWebhook('payment_succeeded', 'unknown-payment-id', 'succeeded')->assertOk();

    $cb = PaymentCallback::query()->where('bank_order_id', 'unknown-payment-id')->first();
    expect($cb)->not->toBeNull()->and($cb->outcome->value)->toBe('unmatched');
});

it('never trusts the webhook body: a succeeded event with a PENDING gateway leaves the order authorising', function () {
    $order = makeAuthorisingOrder('bp-hint');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending, $order->amount_minor);

    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded')->assertOk();
    expect($order->refresh()->status)->toBe(OrderStatus::Authorising); // gateway is authoritative, not the body

    // when the gateway later reports Approved, a recheck settles it
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);
    app(PaymentVerifierService::class)->verifyAndApply($order->refresh());
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('maps a merchant cancellation to cancelled and a decline to failed', function () {
    $cancelled = makeAuthorisingOrder('bp-cancel');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Canceled, $cancelled->amount_minor);
    postWebhook('payment_canceled', $cancelled->bank_order_id, 'canceled')->assertOk();
    expect($cancelled->refresh()->status)->toBe(OrderStatus::Cancelled);

    $failed = makeAuthorisingOrder('bp-decline');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Declined, $failed->amount_minor);
    postWebhook('payment_canceled', $failed->bank_order_id, 'canceled')->assertOk();
    expect($failed->refresh()->status)->toBe(OrderStatus::Failed);
});

it('verifies via getOrderStatus on the browser return and renders the result', function () {
    $order = makeAuthorisingOrder('bp-return');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);

    $res = $this->get('/v1/payments/return?paymentId='.$order->bank_order_id);
    $res->assertOk();
    expect($res->getContent())->toContain('Ödəniş uğurlu');
    expect($order->refresh()->status)->toBe(OrderStatus::Paid); // updated from the authoritative GET, not the query
});

it('exposes payment logs and enriched order detail (bank id + webhook history) to admins', function () {
    seedRbac();
    $order = makeAuthorisingOrder('bp-logs');
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);
    postWebhook('payment_succeeded', $order->bank_order_id, 'succeeded')->assertOk();

    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/payment-logs?direction=inbound')
        ->assertOk()->assertJsonStructure(['data' => [['id', 'direction', 'endpoint', 'signature_valid', 'request']], 'page']);

    $this->actingAs(makeSuperAdmin('s2@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/orders/'.$order->id)
        ->assertOk()
        ->assertJsonPath('bank_order_id', $order->bank_order_id)
        ->assertJsonStructure(['webhooks' => [['bank_status', 'outcome', 'created_at']], 'timeline', 'payments']);
});

it('reconciles a quiet authorising order via the scheduled reconciler', function () {
    $order = makeAuthorisingOrder('bp-recon');
    $order->forceFill(['updated_at' => now()->subHours(2)])->save(); // older than the recheck window
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved, $order->amount_minor);

    $stats = app(OrderReconciler::class)->reconcile();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($stats['resolved'])->toBeGreaterThanOrEqual(1);
});
