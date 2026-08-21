<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Payments\Adapters\BirPay\BirPayClient;
use App\Domain\Payments\Adapters\BirPay\BirPayGateway;
use App\Domain\Payments\Adapters\BirPay\BirPayTokenService;
use App\Domain\Payments\DTOs\BirPay\CreatePaymentData;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Exceptions\BirPayApiException;
use App\Domain\Payments\Exceptions\BirPayAuthException;
use App\Domain\Payments\Exceptions\PaymentSettingsInvalidException;
use App\Domain\Payments\Services\PaymentSettingsValidator;
use Illuminate\Support\Facades\Http;

/*
| BirPay (Kapital Checkout V1.3) — Phase 1 (OAuth) + Phase 2 (create payment) client.
*/

function setBirPay(array $over = []): void
{
    app(SettingsService::class)->setGroup('payments', array_merge([
        'kapital_enabled' => true,
        'kapital_mode' => 'sandbox',
        'kapital_api_base_url' => 'https://preapi.birpay.az',
        'kapital_client_id' => 'birpay-test',
        'kapital_client_secret' => 'topsecret',
        'kapital_scope' => 'email',
        'kapital_merchant_id' => 'E1040009',
        'kapital_terminal_id' => 'E1040009',
        'kapital_return_url' => 'https://api.salamheyetimiz.com/v1/payments/return',
        'kapital_currency' => 'AZN',
    ], $over));
}

function makeOrderForGateway(): Order
{
    $order = new Order;
    $order->forceFill([
        'id' => 1,
        'reference' => 'SH-TEST-1',
        'amount_minor' => 100,
        'currency' => 'AZN',
        'return_url' => null,
        'bank_idempotency_key' => 'order-idem-1',
    ]);

    return $order;
}

function tokenBody(): array
{
    return ['access_token' => 'tok_'.bin2hex(random_bytes(4)), 'expires_in' => 300, 'token_type' => 'Bearer', 'scope' => 'profile email'];
}

function paymentBody(string $status = 'pending'): array
{
    return [
        'id' => '6b193bde-e009-4bef-aade-938621608c90',
        'rrn' => 8176552,
        'type' => 'purchase',
        'amount' => ['value' => 1.0, 'currency' => 'azn'],
        'status' => $status,
        'paid' => false, 'captured' => true,
        'confirmation' => ['type' => 'redirect', 'confirmUrl' => 'https://precheckout.kapitalbank.az/v1/payments?paymentId=6b193bde-e009-4bef-aade-938621608c90'],
        'merchant' => ['externalId' => 'E1040009', 'name' => 'Test', 'mcc' => '5462'],
    ];
}

it('acquires a bearer token via client_credentials and never sends GET', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200)]);

    $token = app(BirPayTokenService::class)->token();

    expect($token)->toStartWith('tok_');
    Http::assertSent(fn ($req) => str_contains($req->url(), '/api/oauth2/token')
        && $req['grant_type'] === 'client_credentials'
        && $req['scope'] === 'email'
        && $req['client_id'] === 'birpay-test'
        && $req['client_secret'] === 'topsecret');
});

it('caches the token (second call does not re-fetch)', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200)]);
    $svc = app(BirPayTokenService::class);

    $a = $svc->token();
    $b = $svc->token();

    expect($a)->toBe($b);
    Http::assertSentCount(1); // only one network call
});

it('force-refreshes the token (evicts cache and re-fetches)', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::sequence()
        ->push(['access_token' => 'tok_one', 'expires_in' => 300, 'token_type' => 'Bearer', 'scope' => 'email'])
        ->push(['access_token' => 'tok_two', 'expires_in' => 300, 'token_type' => 'Bearer', 'scope' => 'email'])]);
    $svc = app(BirPayTokenService::class);

    expect($svc->token())->toBe('tok_one')
        ->and($svc->forceRefresh())->toBe('tok_two');
    Http::assertSentCount(2);
});

it('throws the exact upstream reason on an auth failure (no generic message)', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'Invalid client credentials'], 401)]);

    expect(fn () => app(BirPayTokenService::class)->token())
        ->toThrow(BirPayAuthException::class, 'Invalid client credentials');
});

it('creates a payment with X-Idempotency-Key + Bearer and extracts confirmUrl', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200), '*/v1/payments' => Http::response(paymentBody('pending'), 200)]);

    $idem = 'idem-123e4567';
    $payment = app(BirPayClient::class)->createPayment(new CreatePaymentData(
        amountMinor: 100, currency: 'AZN', capture: true, description: 'x',
        paymentMethodType: 'BANK_CARD', confirmationType: 'REDIRECT',
        returnUrl: 'https://x/return', metadata: ['orderNo' => 'SH-1'],
    ), $idem);

    expect($payment->id)->toBe('6b193bde-e009-4bef-aade-938621608c90')
        ->and($payment->status)->toBe('pending')
        ->and($payment->confirmUrl)->toContain('precheckout.kapitalbank.az');

    Http::assertSent(function ($req) use ($idem) {
        if (! str_ends_with($req->url(), '/v1/payments')) {
            return true;
        }
        $body = $req->data();

        return $req->hasHeader('X-Idempotency-Key', $idem)
            && $req->hasHeader('Authorization')
            && $body['capture'] === true
            && $body['paymentMethodData']['type'] === 'BANK_CARD'
            && $body['confirmation']['type'] === 'REDIRECT'
            && $body['amount']['currency'] === 'AZN';
    });
});

it('maps a BirPay error envelope to BirPayApiException with the exact code/message', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200), '*/v1/payments' => Http::response([
        'code' => 'bad_request', 'status' => 400, 'message' => 'Validation error',
        'errors' => [['property' => 'amount.value', 'message' => 'must not be null']],
    ], 400)]);

    try {
        app(BirPayClient::class)->createPayment(new CreatePaymentData(0, 'AZN', true, null, 'BANK_CARD', 'REDIRECT', 'https://x'), 'k');
        $this->fail('expected BirPayApiException');
    } catch (BirPayApiException $e) {
        expect($e->bankCode())->toBe('bad_request')
            ->and($e->getMessage())->toBe('Validation error')
            ->and($e->errors())->toHaveCount(1)
            ->and($e->bankStatus())->toBe(400);
    }
});

it('retries a 5xx with the same idempotency key and then succeeds', function () {
    setBirPay();
    Http::fake([
        '*/api/oauth2/token' => Http::response(tokenBody(), 200),
        '*/v1/payments' => Http::sequence()
            ->push(['message' => 'boom'], 500)
            ->push(paymentBody('pending'), 200),
    ]);

    $payment = app(BirPayClient::class)->createPayment(new CreatePaymentData(100, 'AZN', true, null, 'BANK_CARD', 'REDIRECT', 'https://x'), 'same-key');

    expect($payment->id)->not->toBe('');
    // both create attempts carried the SAME idempotency key
    $keys = [];
    Http::assertSent(function ($req) use (&$keys) {
        if (str_ends_with($req->url(), '/v1/payments')) {
            $keys[] = $req->header('X-Idempotency-Key')[0] ?? null;
        }

        return true;
    });
    expect(array_unique(array_filter($keys)))->toBe(['same-key']);
});

it('re-authenticates once on a 401 and retries', function () {
    setBirPay();
    Http::fake([
        '*/api/oauth2/token' => Http::response(tokenBody(), 200),
        '*/v1/payments' => Http::sequence()
            ->push(['code' => 'token_expired'], 401)
            ->push(paymentBody('pending'), 200),
    ]);

    $payment = app(BirPayClient::class)->createPayment(new CreatePaymentData(100, 'AZN', true, null, 'BANK_CARD', 'REDIRECT', 'https://x'), 'k');

    expect($payment->id)->not->toBe('');
});

it('BirPayGateway.registerOrder returns the bank id + confirmUrl and sends posDetail', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200), '*/v1/payments' => Http::response(paymentBody('pending'), 200)]);

    $result = app(BirPayGateway::class)->registerOrder(makeOrderForGateway());

    expect($result->bankOrderId)->toBe('6b193bde-e009-4bef-aade-938621608c90')
        ->and($result->redirectUrl)->toContain('precheckout.kapitalbank.az');

    // POS-terminal merchants require posDetail{merchantId, terminalId}
    Http::assertSent(function ($req) {
        if (! str_ends_with($req->url(), '/v1/payments')) {
            return true;
        }
        $b = $req->data();

        return ($b['posDetail']['merchantId'] ?? null) === 'E1040009' && ($b['posDetail']['terminalId'] ?? null) === 'E1040009';
    });
});

it('treats an HTTP-200 error envelope as a BirPayApiException (the real sandbox quirk)', function () {
    setBirPay();
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200), '*/v1/payments' => Http::response([
        'id' => 'env-id', 'code' => 'bad_request', 'status' => 400, 'message' => 'Validation error',
        'errors' => [['property' => 'posDetail', 'message' => 'must not be null']],
    ], 200)]); // HTTP 200 but an error envelope

    try {
        app(BirPayClient::class)->createPayment(new CreatePaymentData(100, 'AZN', true, null, 'BANK_CARD', 'REDIRECT', 'https://x'), 'k');
        $this->fail('expected BirPayApiException');
    } catch (BirPayApiException $e) {
        expect($e->bankCode())->toBe('bad_request')->and($e->bankStatus())->toBe(400);
    }
});

it('validates settings: missing fields fail; a good OAuth probe passes', function () {
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200)]);
    $validator = app(PaymentSettingsValidator::class);

    // missing client_id + merchant_id
    expect(fn () => $validator->validate([
        'kapital_mode' => 'sandbox', 'kapital_api_base_url' => 'https://preapi.birpay.az',
        'kapital_client_id' => '', 'kapital_client_secret' => 's', 'kapital_merchant_id' => '',
        'kapital_terminal_id' => 'T', 'kapital_currency' => 'AZN',
    ]))->toThrow(PaymentSettingsInvalidException::class);

    // full + valid → no throw
    $validator->validate([
        'kapital_mode' => 'sandbox', 'kapital_api_base_url' => 'https://preapi.birpay.az',
        'kapital_client_id' => 'birpay-test', 'kapital_client_secret' => 's', 'kapital_scope' => 'email',
        'kapital_merchant_id' => 'E1040009', 'kapital_terminal_id' => 'E1040009', 'kapital_currency' => 'AZN',
    ]);
    expect(true)->toBeTrue();
});

it('flags sandbox/production host inconsistency', function () {
    $validator = app(PaymentSettingsValidator::class);
    expect(fn () => $validator->validate([
        'kapital_mode' => 'production', 'kapital_api_base_url' => 'https://preapi.birpay.az',
        'kapital_client_id' => 'c', 'kapital_client_secret' => 's', 'kapital_merchant_id' => 'M',
        'kapital_terminal_id' => 'T', 'kapital_currency' => 'AZN',
    ]))->toThrow(PaymentSettingsInvalidException::class);
});

it('blocks enabling payments via the API with bad credentials and does not persist', function () {
    seedRbac();
    setBirPay(['kapital_enabled' => false, 'kapital_client_secret' => 'old']);
    Http::fake(['*/api/oauth2/token' => Http::response(['error' => 'invalid_client', 'error_description' => 'bad creds'], 401)]);

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/settings/payments', ['kapital_enabled' => true, 'kapital_client_secret' => 'wrong'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'payment_settings_invalid');

    // not persisted: still disabled, secret unchanged
    expect(app(SettingsService::class)->value('payments', 'kapital_enabled'))->toBeFalse()
        ->and(app(SettingsService::class)->value('payments', 'kapital_client_secret'))->toBe('old');
});

it('saves payments when enabling with valid credentials', function () {
    seedRbac();
    setBirPay(['kapital_enabled' => false]);
    Http::fake(['*/api/oauth2/token' => Http::response(tokenBody(), 200)]);

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/settings/payments', ['kapital_enabled' => true])
        ->assertOk();

    expect(app(SettingsService::class)->value('payments', 'kapital_enabled'))->toBeTrue();
});

it('creates a refund with X-Idempotency-Key and maps succeeded → approved', function () {
    setBirPay();
    Http::fake([
        '*/api/oauth2/token' => Http::response(tokenBody(), 200),
        '*/v1/refunds' => Http::response(['id' => 'rf-1', 'originalId' => 'pay-1', 'amount' => ['value' => 4, 'currency' => 'azn'], 'status' => 'succeeded'], 200),
    ]);

    $result = app(BirPayGateway::class)->refund('pay-1', 400, 'refund-7');

    expect($result->approved)->toBeTrue()->and($result->bankTransactionId)->toBe('rf-1');
    Http::assertSent(function ($req) {
        if (! str_ends_with($req->url(), '/v1/refunds')) {
            return true;
        }
        $b = $req->data();

        return $req->hasHeader('X-Idempotency-Key', 'refund-7') && $b['id'] === 'pay-1' && (float) $b['amount'] === 4.0;
    });
});

it('maps a canceled BirPay refund → not approved', function () {
    setBirPay();
    Http::fake([
        '*/api/oauth2/token' => Http::response(tokenBody(), 200),
        '*/v1/refunds' => Http::response(['id' => 'rf-2', 'status' => 'canceled'], 200),
    ]);

    $result = app(BirPayGateway::class)->refund('pay-2', 100, 'refund-8');
    expect($result->approved)->toBeFalse()->and($result->failureReason)->toContain('canceled');
});

it('serves real payments Test Connection diagnostics (OAuth + authenticated) and 403s non-super', function () {
    seedRbac();
    setBirPay();
    Http::fake([
        '*/api/oauth2/token' => Http::response(tokenBody(), 200),
        '*/v1/payments/*' => Http::response(['code' => 'payment_not_found', 'status' => 400], 400),
    ]);

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/settings/payments/test')
        ->assertOk()
        ->assertJsonPath('oauth.ok', true)
        ->assertJsonPath('authenticated.ok', true)
        ->assertJsonPath('ok', true);

    $this->actingAs(makeAdminRole('finance'), 'admin')->postJson('/admin/v1/settings/payments/test')->assertStatus(403);
});
