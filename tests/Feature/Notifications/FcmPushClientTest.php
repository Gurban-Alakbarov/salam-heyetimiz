<?php

use App\Domain\Notifications\Adapters\FakePushClient;
use App\Domain\Notifications\Adapters\FcmPushClient;
use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Enums\PushDeliveryStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
| FcmPushClient (FCM HTTP v1): hybrid payload shape, outcome→PushDeliveryStatus mapping (R-NOT-14),
| and the preserved test-environment FakePushClient binding (R-ARCH-08). No real network or credential
| — the OAuth2 token is pre-seeded into the cache so google/auth is never invoked.
*/

function fcmClient(): FcmPushClient
{
    Cache::put(FcmPushClient::TOKEN_CACHE_KEY, 'test-access-token', 3600);

    return new FcmPushClient('salam-heyetimiz', 'unused-in-test.json', 10);
}

function samplePush(): array
{
    return [
        'token' => 'device-token-xyz',
        'notification' => ['title' => 'Qonaq daxil oldu', 'body' => 'Kuryer baryeri açdı'],
        'data' => [
            'type' => 'visitor_link_used',
            'notification_id' => '4242',
            'ids' => ['visitor_link_id' => 5, 'device_id' => 3],
        ],
    ];
}

it('sends the hybrid message to FCM HTTP v1 and reports Delivered', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/salam-heyetimiz/messages/1'], 200)]);
    $p = samplePush();

    $status = fcmClient()->send($p['token'], $p['notification'], $p['data']);

    expect($status)->toBe(PushDeliveryStatus::Delivered);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/salam-heyetimiz/messages:send'
            && $request->hasHeader('Authorization', 'Bearer test-access-token')
            && $body['message']['token'] === 'device-token-xyz'
            && $body['message']['notification'] === ['title' => 'Qonaq daxil oldu', 'body' => 'Kuryer baryeri açdı']
            && $body['message']['data']['type'] === 'visitor_link_used'
            && $body['message']['data']['notification_id'] === '4242'
            && json_decode($body['message']['data']['ids'], true) === ['visitor_link_id' => 5, 'device_id' => 3];
    });
});

it('never puts a JWT, auth token, or extra PII in the FCM data map', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'ok'], 200)]);
    $p = samplePush();

    fcmClient()->send($p['token'], $p['notification'], $p['data']);

    Http::assertSent(function ($request) {
        // Exactly the three model keys — nothing else leaks into the transport.
        return array_keys($request->data()['message']['data']) === ['type', 'notification_id', 'ids'];
    });
});

it('maps an UNREGISTERED token to TokenInvalid', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response([
        'error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]],
    ], 404)]);
    $p = samplePush();

    expect(fcmClient()->send($p['token'], $p['notification'], $p['data']))->toBe(PushDeliveryStatus::TokenInvalid);
});

it('does not invalidate the token on a 400 INVALID_ARGUMENT (message error, not a dead token)', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response([
        'error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'details' => [['errorCode' => 'INVALID_ARGUMENT']]],
    ], 400)]);
    $p = samplePush();

    expect(fcmClient()->send($p['token'], $p['notification'], $p['data']))->toBe(PushDeliveryStatus::Failed);
});

it('maps a transient 5xx to Failed', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response('', 503)]);
    $p = samplePush();

    expect(fcmClient()->send($p['token'], $p['notification'], $p['data']))->toBe(PushDeliveryStatus::Failed);
});

it('maps an auth 401 to Failed (config issue — token untouched)', function () {
    Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401)]);
    $p = samplePush();

    expect(fcmClient()->send($p['token'], $p['notification'], $p['data']))->toBe(PushDeliveryStatus::Failed);
});

it('keeps FakePushClient as the bound transport in the test environment', function () {
    expect(app(PushClient::class))->toBeInstanceOf(FakePushClient::class);
});
