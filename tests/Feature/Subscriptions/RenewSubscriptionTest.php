<?php

use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;

/*
| POST /v1/subscriptions/{id}/renew — renewSubscription (creates a tier-priced order; 409 / 404).
*/

it('creates a renewal order for an active subscription', function () {
    $me = makeUser('+994500000030');
    $device = makeActiveDevice('SER-R1', '+994700000030');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du); // active, main → 1200

    $response = $this->actingAs($me, 'user')->postJson(
        "/v1/subscriptions/{$sub->id}/renew",
        ['return_url' => 'salam://payment/return'],
        ['Idempotency-Key' => 'renew-1'],
    );

    $response->assertOk()
        ->assertJsonPath('purpose', 'sub_renewal')
        ->assertJsonPath('amount_minor', 1200)
        ->assertJsonPath('status', 'authorising');

    expect($response->json('bank_redirect_url'))->not->toBeNull();

    $order = Order::query()->firstOrFail();
    expect((int) $order->items()->first()->referenced_id)->toBe($sub->id)
        ->and($order->items()->first()->item_type->value)->toBe('sub_main');
});

it('allows renewing an expired subscription', function () {
    $me = makeUser('+994500000031');
    $device = makeActiveDevice('SER-R2', '+994700000031');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $this->actingAs($me, 'user')->postJson(
        "/v1/subscriptions/{$sub->id}/renew",
        [],
        ['Idempotency-Key' => 'renew-2'],
    )->assertOk()->assertJsonPath('status', 'authorising');
});

it('rejects renewing a pending-payment subscription with 409', function () {
    $me = makeUser('+994500000032');
    $device = makeActiveDevice('SER-R3', '+994700000032');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du, ['status' => SubscriptionStatus::PendingPayment, 'ends_at' => now()]);

    $this->actingAs($me, 'user')->postJson(
        "/v1/subscriptions/{$sub->id}/renew",
        [],
        ['Idempotency-Key' => 'renew-3'],
    )->assertStatus(409)->assertJsonPath('error.code', 'subscription_not_renewable');
});

it('requires an Idempotency-Key', function () {
    $me = makeUser('+994500000033');
    $device = makeActiveDevice('SER-R4', '+994700000033');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->postJson("/v1/subscriptions/{$sub->id}/renew", [])
        ->assertStatus(422);
});

it('hides another user’s subscription behind a 404 on renew', function () {
    $me = makeUser('+994500000034');
    $other = makeUser('+994500000035');
    $device = makeActiveDevice('SER-R5', '+994700000034');
    $du = makeDeviceUser($other, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->postJson(
        "/v1/subscriptions/{$sub->id}/renew",
        [],
        ['Idempotency-Key' => 'renew-5'],
    )->assertStatus(404);
});
