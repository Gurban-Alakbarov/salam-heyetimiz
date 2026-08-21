<?php

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;

/*
| GET /v1/subscriptions/{id} — getSubscription (SubscriptionDetail; owner-only, 404 otherwise).
*/

it('returns the subscription detail with periods and latest_order for the owner', function () {
    $me = makeUser('+994500000020');
    $device = makeActiveDevice('SER-D1', '+994700000020');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du);

    $order = makePaidOrder($me, 1200, 'KB-DETAIL-1');
    $sub->periods()->create([
        'order_id' => $order->id,
        'kind' => SubscriptionPeriodKind::Initial,
        'period_start' => now(),
        'period_end' => now()->addDays(365),
        'amount_minor' => 1200,
    ]);

    $response = $this->actingAs($me, 'user')->getJson("/v1/subscriptions/{$sub->id}");

    $response->assertOk()
        ->assertJsonPath('id', $sub->id)
        ->assertJsonPath('tier', 'main')
        ->assertJsonPath('device_id', $device->id)
        ->assertJsonCount(1, 'periods')
        ->assertJsonPath('periods.0.kind', 'initial')
        ->assertJsonPath('latest_order.id', $order->id)
        ->assertJsonPath('latest_order.status', OrderStatus::Paid->value);

    expect($response->json('days_remaining'))->toBeGreaterThan(360);
});

it('hides another user’s subscription behind a 404', function () {
    $me = makeUser('+994500000021');
    $other = makeUser('+994500000022');
    $device = makeActiveDevice('SER-D2', '+994700000021');
    $du = makeDeviceUser($other, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->getJson("/v1/subscriptions/{$sub->id}")->assertStatus(404);
});

it('returns 404 for a missing subscription', function () {
    $me = makeUser('+994500000023');

    $this->actingAs($me, 'user')->getJson('/v1/subscriptions/999999')->assertStatus(404);
});
