<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Payments\Adapters\KapitalEcom\KapitalEcomClient;
use App\Domain\Payments\Adapters\KapitalEcom\KapitalEcomGateway;
use App\Domain\Payments\Adapters\PaymentGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Exceptions\KapitalEcomApiException;
use App\Domain\Payments\Exceptions\PaymentProviderUnavailableException;
use App\Domain\Payments\Exceptions\PaymentSettingsInvalidException;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\PaymentLog;
use App\Domain\Payments\Services\OrderReconciler;
use App\Domain\Payments\Services\PaymentSettingsValidator;
use App\Domain\Payments\Support\PaymentGatewayMode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| Kapital e-commerce API adapter (docs/integrations/kapital/ecom/API_REFERENCE.md). Response shapes are the
| ones the bank's test host returned.
*/

const KAPITAL_HOST = 'https://txpgtst.kapitalbank.az/api';

function setKapitalEcom(array $over = []): void
{
    app(SettingsService::class)->setGroup('payments', array_merge([
        'kapital_mode' => 'sandbox',
        'kapital_ecom_username' => 'merchant/login',
        'kapital_ecom_password' => 'pw',
        'kapital_return_url' => 'https://api.sezam.salam.az/v1/payments/return',
        'kapital_currency' => 'AZN',
    ], $over));
}

function kapitalGateway(): KapitalEcomGateway
{
    return app(KapitalEcomGateway::class);
}

function kapitalOrder(array $over = []): Order
{
    $order = new Order;
    $order->forceFill(array_merge([
        'id' => 1,
        'reference' => 'SH-TEST-1',
        'amount_minor' => 1500,
        'currency' => 'AZN',
        'return_url' => null,
    ], $over));

    return $order;
}

function kapitalPaidBody(array $over = []): array
{
    return ['order' => array_merge([
        'id' => 294074,
        'status' => 'FullyPaid',
        'password' => 'order-password',
        'secret' => '607694',
        'amount' => 15,
        'currency' => 'AZN',
        'clearedChargeAmount' => 15,
        'clearedRefundAmount' => 0,
        'srcToken' => ['displayName' => '416974******1778', 'card' => ['brand' => 'Visa']],
        'trans' => [
            ['type' => 'Purchase', 'approvalCode' => '053703', 'actionId' => '240612-1', 'ridByPmo' => '17579346', 'rrn' => '954734574484'],
        ],
    ], $over)];
}

it('creates an Order_SMS with a decimal amount over Basic auth and builds the hosted page url', function () {
    setKapitalEcom();
    Http::fake([KAPITAL_HOST.'/order' => Http::response(['order' => [
        'id' => 294074, 'hppUrl' => 'https://txpgtst.kapitalbank.az/flex', 'password' => '1n45 dyll', 'status' => 'Preparing', 'secret' => '607694',
    ]])]);

    $result = kapitalGateway()->registerOrder(kapitalOrder());

    expect($result->bankOrderId)->toBe('294074')
        ->and($result->redirectUrl)->toBe('https://txpgtst.kapitalbank.az/flex?id=294074&password=1n45%20dyll');

    Http::assertSent(function (Request $request) {
        $order = $request->data()['order'];

        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant/login:pw'))
            && $order['typeRid'] === 'Order_SMS'
            && $order['amount'] === '15.00'
            && $order['currency'] === 'AZN'
            && $order['description'] === 'Order SH-TEST-1'
            && $order['hppRedirectUrl'] === 'https://api.sezam.salam.az/v1/payments/return';
    });
});

it('uses the production host in production mode', function () {
    setKapitalEcom(['kapital_mode' => 'production']);
    Http::fake(['https://e-commerce.kapitalbank.az/api/order' => Http::response(['order' => [
        'id' => 7, 'hppUrl' => 'https://e-commerce.kapitalbank.az/flex', 'password' => 'p', 'status' => 'Preparing',
    ]])]);

    expect(kapitalGateway()->registerOrder(kapitalOrder())->bankOrderId)->toBe('7');
});

it('never repeats a create order: the API has no idempotency key', function () {
    setKapitalEcom();
    Http::fake([KAPITAL_HOST.'/order' => Http::response(['errorCode' => 'ServiceError', 'errorDescription' => 'down'], 500)]);

    expect(fn () => kapitalGateway()->registerOrder(kapitalOrder()))->toThrow(PaymentProviderUnavailableException::class);
    Http::assertSentCount(1);
});

it('never writes the order password or secret to the payment log', function () {
    setKapitalEcom();
    Http::fake([KAPITAL_HOST.'/order' => Http::response(['order' => [
        'id' => 294074, 'hppUrl' => 'https://txpgtst.kapitalbank.az/flex', 'password' => 'order-password', 'status' => 'Preparing', 'secret' => '607694',
    ]])]);

    kapitalGateway()->registerOrder(makePaidOrder(makeUser(), 1500, 'log-1'));

    $log = PaymentLog::query()->latest('id')->firstOrFail();
    expect((string) $log->response_redacted_encrypted)->toContain('294074')
        ->not->toContain('order-password')
        ->not->toContain('607694');
});

it('reads a paid order with its card and transaction details', function () {
    setKapitalEcom();
    Http::fake([KAPITAL_HOST.'/order/294074*' => Http::response(kapitalPaidBody())]);

    $status = kapitalGateway()->getOrderStatus('294074');

    expect($status->status)->toBe(BankStatus::Approved)
        ->and($status->amountMinor)->toBe(1500)
        ->and($status->rrn)->toBe('954734574484')
        ->and($status->bankTransactionId)->toBe('954734574484')
        ->and($status->approvalCode)->toBe('053703')
        ->and($status->cardBrand)->toBe('Visa')
        ->and($status->panMasked)->toBe('416974******1778')
        ->and(json_encode($status->raw))->not->toContain('order-password')->not->toContain('607694');
});

it('maps the bank order statuses', function (array $order, BankStatus $expected) {
    expect(KapitalEcomGateway::mapStatus($order))->toBe($expected);
})->with([
    'paid' => [['status' => 'FullyPaid'], BankStatus::Approved],
    'preparing' => [['status' => 'Preparing'], BankStatus::Pending],
    'authorized' => [['status' => 'Authorized'], BankStatus::Pending],
    'partly paid, nothing captured' => [['status' => 'PartiallyPaid'], BankStatus::Pending],
    'after a partial refund' => [['status' => 'PartPaid', 'clearedChargeAmount' => 15, 'clearedRefundAmount' => 5], BankStatus::Approved],
    'declined' => [['status' => 'Declined'], BankStatus::Declined],
    'rejected' => [['status' => 'Rejected'], BankStatus::Declined],
    'cancelled' => [['status' => 'Cancelled'], BankStatus::Canceled],
    'refused' => [['status' => 'Refused'], BankStatus::Canceled],
    'expired' => [['status' => 'Expired'], BankStatus::Canceled],
    'refunded' => [['status' => 'Refunded'], BankStatus::Refunded],
    'voided' => [['status' => 'Voided'], BankStatus::Refunded],
    'closed with money' => [['status' => 'Closed', 'clearedChargeAmount' => 15, 'clearedRefundAmount' => 0], BankStatus::Approved],
    'closed fully refunded' => [['status' => 'Closed', 'clearedChargeAmount' => 15, 'clearedRefundAmount' => 15], BankStatus::Refunded],
    'unknown' => [['status' => 'SomethingNew'], BankStatus::Pending],
]);

it('retries a read after a 5xx', function () {
    setKapitalEcom();
    Http::fakeSequence(KAPITAL_HOST.'/order/*')
        ->push(['errorCode' => 'ServiceError', 'errorDescription' => 'busy'], 500)
        ->push(kapitalPaidBody());

    expect(kapitalGateway()->getOrderStatus('294074')->status)->toBe(BankStatus::Approved);
});

it('keeps the reconciler running when the bank does not know an order', function () {
    setKapitalEcom();
    app()->instance(PaymentGateway::class, kapitalGateway());
    Http::fake([KAPITAL_HOST.'/order/*' => Http::response(['errorCode' => 'ServiceError', 'errorDescription' => 'no order found'], 404)]);

    $order = makePaidOrder(makeUser(), 1500, 'unknown-at-bank');
    $order->forceFill(['status' => 'authorising', 'expires_at' => now()->subHour(), 'updated_at' => now()->subHour()])->saveQuietly();

    expect(app(OrderReconciler::class)->reconcile()['resolved'])->toBe(0);
});

it('refunds a decimal amount through exec-tran', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response(['tran' => [
            'approvalCode' => '963348', 'match' => ['tranActionId' => '240612-2', 'ridByPmo' => '17579348'], 'pmoResultCode' => '1',
        ]]),
        KAPITAL_HOST.'/order/*' => Http::response(kapitalPaidBody()),
    ]);

    $result = kapitalGateway()->refund('294074', 500, 'refund-1');

    expect($result->approved)->toBeTrue()->and($result->bankTransactionId)->toBe('17579348');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/exec-tran')
        && $r->data()['tran'] === ['phase' => 'Single', 'amount' => '5.00', 'type' => 'Refund']);
});

it('takes the RRN of a refund from the order, because the refund answer has none', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response(['tran' => ['approvalCode' => '244278', 'match' => ['ridByPmo' => '28179940'], 'pmoResultCode' => '1']]),
        KAPITAL_HOST.'/order/294119?*' => Http::sequence()
            ->push(kapitalPaidBody())
            ->push(kapitalPaidBody(['status' => 'PartPaid', 'trans' => [
                ['type' => 'Purchase', 'ridByPmo' => '28179937', 'rrn' => '338944575996'],
                ['type' => 'Refund', 'ridByPmo' => '28179940', 'rrn' => '352774575998'],
            ]])),
    ]);

    expect(kapitalGateway()->refund('294119', 500, 'refund-1')->bankTransactionId)->toBe('352774575998');
});

it('keeps a done refund when reading its RRN fails', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response(['tran' => ['match' => ['ridByPmo' => '28179940'], 'pmoResultCode' => '1']]),
        KAPITAL_HOST.'/order/294119?*' => Http::sequence()
            ->push(kapitalPaidBody())
            ->whenEmpty(Http::response(['errorCode' => 'ServiceError', 'errorDescription' => 'busy'], 500)),
    ]);

    $result = kapitalGateway()->refund('294119', 500, 'refund-1');

    expect($result->approved)->toBeTrue()->and($result->bankTransactionId)->toBe('28179940');
});

it('does not send a refund again when the bank already made it and the answer was lost', function () {
    setKapitalEcom();
    Http::fake([KAPITAL_HOST.'/order/*' => Http::response(kapitalPaidBody([
        'status' => 'Refunded',
        'clearedRefundAmount' => 15,
        'trans' => [['type' => 'Purchase', 'ridByPmo' => '1'], ['type' => 'Refund', 'ridByPmo' => '2']],
    ]))]);
    makePaidOrder(makeUser(), 1500, '294074');

    $result = kapitalGateway()->refund('294074', 1500, 'refund-1');

    expect($result->approved)->toBeTrue()->and($result->bankTransactionId)->toBe('2');
    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/exec-tran'));
});

it('counts earlier recorded refunds, so a second partial refund is still sent', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response(['tran' => ['match' => ['ridByPmo' => '3'], 'pmoResultCode' => '1']]),
        KAPITAL_HOST.'/order/*' => Http::response(kapitalPaidBody(['clearedRefundAmount' => 5])),
    ]);
    $order = makePaidOrder(makeUser(), 1500, '294074');
    $order->payments()->create([
        'bank_transaction_id' => 'first', 'type' => 'refund', 'amount_minor' => -500, 'currency' => 'AZN',
        'status' => 'approved', 'occurred_at' => now(),
    ]);

    expect(kapitalGateway()->refund('294074', 500, 'refund-2')->approved)->toBeTrue();
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/exec-tran'));
});

it('reports a refund the bank declined', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response(['errorCode' => 'PmoDecline', 'errorDescription' => 'Transaction declined by PMO'], 500),
        KAPITAL_HOST.'/order/*' => Http::response(kapitalPaidBody()),
    ]);

    $result = kapitalGateway()->refund('294074', 500, 'refund-1');

    expect($result->approved)->toBeFalse()->and($result->failureReason)->toContain('PmoDecline');
});

it('leaves a refund with an unknown outcome to the job retry', function () {
    setKapitalEcom();
    Http::fake([
        KAPITAL_HOST.'/order/*/exec-tran' => Http::response('', 502),
        KAPITAL_HOST.'/order/*' => Http::response(kapitalPaidBody()),
    ]);

    expect(fn () => kapitalGateway()->refund('294074', 500, 'refund-1'))->toThrow(KapitalEcomApiException::class);
});

it('finds the order from the ID the bank sends to the return page', function () {
    $order = makePaidOrder(makeUser(), 1500, 'bank-ret-1');

    $this->get('/v1/payments/return?ID=bank-ret-1&STATUS=FullyPaid')
        ->assertOk()
        ->assertSee('status=success', false)
        ->assertSee($order->reference, false);
});

it('accepts a login the bank knows and shows the exact reason otherwise', function () {
    Http::fake(['*/order/999999999' => Http::sequence()
        ->push(['errorCode' => 'ServiceError', 'errorDescription' => 'no order found'], 404)
        ->push(['errorCode' => 'ServiceError', 'errorDescription' => 'ApeError.QueryAppError.InvalidLogin'], 500)]);
    $validator = app(PaymentSettingsValidator::class);
    $settings = ['kapital_ecom_username' => 'merchant/login', 'kapital_ecom_password' => 'pw', 'kapital_currency' => 'AZN'];

    $validator->validate($settings);

    expect(fn () => $validator->validate($settings))->toThrow(PaymentSettingsInvalidException::class, 'InvalidLogin');
});

it('reports the e-commerce provider in the diagnostics without creating anything', function () {
    Http::fake(['*/order/999999999' => Http::response(['errorCode' => 'ServiceError', 'errorDescription' => 'no order found'], 404)]);

    $d = app(PaymentSettingsValidator::class)->diagnose(['kapital_ecom_username' => 'merchant/login', 'kapital_ecom_password' => 'pw']);

    expect($d['ok'])->toBeTrue()
        ->and($d['provider'])->toBe(PaymentGatewayMode::KAPITAL)
        ->and($d['host'])->toBe(KAPITAL_HOST);
    Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
});

it('converts money both ways without float errors', function () {
    expect(KapitalEcomClient::decimal(1500))->toBe('15.00')
        ->and(KapitalEcomClient::decimal(1))->toBe('0.01')
        ->and(KapitalEcomClient::minor(19.99))->toBe(1999)
        ->and(KapitalEcomClient::minor('15.10'))->toBe(1510);
});
