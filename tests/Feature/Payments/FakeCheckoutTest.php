<?php

use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\PaymentCallback;
use App\Domain\Payments\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
| IMPLEMENTATION_PLAN B1 — simulated hosted checkout ("TEST ÖDƏNİŞ"). The order goes through the real
| OrderService → FakeKapitalGateway::registerOrder → signed checkout page → simulated bank outcome →
| the SAME callback pipeline as the BirPay webhook → authoritative getOrderStatus → existing return page.
| Runtime fake mode defaults to PENDING (nothing is paid until simulated); mirrored here.
*/

beforeEach(function () {
    Http::fake(); // any outbound request would be recorded — asserted empty
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
});

function createFakeOrder(): Order
{
    $user = makeUser('+99450'.random_int(1000000, 9999999));
    test()->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'fake-'.uniqid()])->assertStatus(201);

    return Order::query()->latest('id')->firstOrFail();
}

function fakeAction(Order $order, string $action): \Illuminate\Testing\TestResponse
{
    $url = URL::temporarySignedRoute('fakeCheckoutAction', now()->addMinutes(30), ['reference' => $order->reference, 'action' => $action], false);

    return test()->post($url);
}

it('registers a fake order: signed TEST checkout URL, fake bank id, is_test flag, no bank request', function () {
    $user = makeUser();
    $response = $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'fake-register-1']);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'authorising')
        ->assertJsonPath('is_test', true);

    expect($response->json('bank_order_id'))->toStartWith(FakeKapitalGateway::BANK_ORDER_PREFIX)
        ->and($response->json('bank_redirect_url'))->toContain('/v1/payments/fake-checkout/')
        ->and($response->json('bank_redirect_url'))->toContain('signature=');

    Http::assertNothingSent();
});

it('renders the simulated hosted page with an explicit TEST ÖDƏNİŞ banner', function () {
    $order = createFakeOrder();

    $this->get($order->bank_redirect_url)
        ->assertOk()
        ->assertSee('TEST ÖDƏNİŞ')
        ->assertSee($order->reference)
        ->assertSee('12.00');
});

it('rejects unsigned or tampered checkout links', function () {
    $order = createFakeOrder();

    $this->get('/v1/payments/fake-checkout/'.$order->reference)->assertForbidden();
    $this->post('/v1/payments/fake-checkout/'.$order->reference.'/pay')->assertForbidden();
    expect($order->fresh()->status)->toBe(OrderStatus::Authorising);
});

it('pay → same callback pipeline → order paid, OrderPaid fired, return page shows TEST ÖDƏNİŞ', function () {
    Event::fake([OrderPaid::class]);
    $order = createFakeOrder();

    fakeAction($order, 'pay')->assertRedirect(route('paymentReturn', ['paymentId' => $order->bank_order_id]));

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->payments()->count())->toBe(1)
        ->and(PaymentCallback::query()->where('bank_order_id', $order->bank_order_id)->where('bank_status', 'APPROVED')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'payment.fake_checkout_action')->exists())->toBeTrue();
    Event::assertDispatched(OrderPaid::class);

    $this->get('/v1/payments/return?paymentId='.$order->bank_order_id)
        ->assertOk()
        ->assertSee('TEST ÖDƏNİŞ')
        ->assertSee('Ödəniş uğurlu')
        ->assertSee('status=success', false)
        ->assertSee('test=1', false);

    Http::assertNothingSent();
});

it('decline → failed', function () {
    $order = createFakeOrder();
    fakeAction($order, 'decline')->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Failed)
        ->and($order->fresh()->failed_reason)->toBe('declined');
    $this->get('/v1/payments/return?paymentId='.$order->bank_order_id)->assertSee('status=failure', false);
});

it('cancel → cancelled', function () {
    $order = createFakeOrder();
    fakeAction($order, 'cancel')->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    $this->get('/v1/payments/return?paymentId='.$order->bank_order_id)->assertSee('status=cancel', false);
});

it('pending → order stays authorising and the return page reports pending; a later pay settles it', function () {
    $order = createFakeOrder();
    fakeAction($order, 'pending')->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Authorising);
    $this->get('/v1/payments/return?paymentId='.$order->bank_order_id)->assertSee('status=pending', false);

    fakeAction($order, 'pay')->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('is idempotent: acting on a settled order changes nothing', function () {
    $order = createFakeOrder();
    fakeAction($order, 'pay');
    fakeAction($order, 'pay');
    fakeAction($order, 'decline');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payments()->count())->toBe(1);
});

it('expired orders stay expired (existing expiry sweep) and report failure', function () {
    $order = createFakeOrder();
    $this->travel(31)->minutes();
    app(OrderService::class)->expireStale();

    expect($order->fresh()->status)->toBe(OrderStatus::Expired);
    fakeAction($order, 'pay')->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Expired);
    $this->get('/v1/payments/return?paymentId='.$order->bank_order_id)->assertSee('status=failure', false);
});

it('refuses to simulate a non-fake (real-bank) order', function () {
    $order = createFakeOrder();
    $order->forceFill(['bank_order_id' => 'bp-real-123'])->save();

    fakeAction($order, 'pay')->assertNotFound();
    expect($order->fresh()->status)->toBe(OrderStatus::Authorising)
        ->and(\App\Http\Resources\OrderResource::make($order->fresh())->resolve()['is_test'])->toBeFalse();
});
