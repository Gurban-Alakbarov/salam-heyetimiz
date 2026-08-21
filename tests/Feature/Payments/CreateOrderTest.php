<?php

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Events\OrderAuthorising;
use App\Domain\Payments\Events\OrderCreated;
use App\Domain\Payments\Models\Order;
use Illuminate\Support\Facades\Event;

it('creates a subscription order, prices it server-side, and registers it with the bank', function () {
    $user = makeUser();
    Event::fake([OrderCreated::class, OrderAuthorising::class]);

    $response = $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'idem-create-1']);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'authorising')
        ->assertJsonPath('amount_minor', 1200)
        ->assertJsonPath('currency', 'AZN');

    expect($response->json('bank_redirect_url'))->not->toBeNull();

    $order = Order::query()->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Authorising)
        ->and($order->bank_order_id)->not->toBeNull()
        ->and($order->items()->count())->toBe(1)
        ->and((int) $order->items()->first()->total_amount_minor)->toBe(1200)
        ->and($order->reference)->toStartWith('SH-');

    Event::assertDispatched(OrderCreated::class);
    Event::assertDispatched(OrderAuthorising::class);
});

it('is idempotent on the Idempotency-Key', function () {
    $user = makeUser();
    $payload = ['purpose' => 'sub_main', 'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]]];

    $first = $this->actingAs($user, 'user')->postJson('/v1/orders', $payload, ['Idempotency-Key' => 'same-key']);
    $second = $this->actingAs($user, 'user')->postJson('/v1/orders', $payload, ['Idempotency-Key' => 'same-key']);

    expect($first->json('id'))->toBe($second->json('id'))
        ->and(Order::query()->count())->toBe(1);
});

it('requires an Idempotency-Key', function () {
    $user = makeUser();

    $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]],
    ])->assertStatus(422);
});

it('allows a device_sale that references an unassigned device (R-DOM-13)', function () {
    $user = makeUser();
    $device = makeUnassignedDevice();

    $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'device_sale',
        'items' => [['item_type' => 'device', 'referenced_id' => $device->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'dev-1'])
        ->assertStatus(201)
        ->assertJsonPath('amount_minor', 13500);
});

it('rejects a device_sale whose device is not unassigned (R-DOM-13)', function () {
    $user = makeUser();
    $device = makeUnassignedDevice();
    $device->update(['status' => 'active']);

    $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'device_sale',
        'items' => [['item_type' => 'device', 'referenced_id' => $device->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'dev-2'])
        ->assertStatus(422);
});

it('denies order creation without an authenticated user (fails closed)', function () {
    // The mobile JWT guard now runs ahead of the FormRequest → 401 (was 403 pre-guard).
    $this->postJson('/v1/orders', [
        'purpose' => 'sub_main',
        'items' => [['item_type' => 'sub_main', 'referenced_id' => 1, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'noauth'])->assertStatus(401);
});

it('validates the request body against the OpenAPI schema', function () {
    $user = makeUser();

    $this->actingAs($user, 'user')->postJson('/v1/orders', [
        'purpose' => 'not_a_purpose',
        'items' => [],
    ], ['Idempotency-Key' => 'bad'])->assertStatus(422);
});
