<?php

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Models\Refund;

it('processes a full refund: bank refund, negative payment row, order refunded', function () {
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-PAID-1');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 1200,
        'reason' => 'customer request',
    ], ['Idempotency-Key' => 'rf-full'])
        ->assertStatus(201)
        ->assertJsonPath('amount_minor', 1200)
        ->assertJsonPath('status', 'requested'); // 201 = refund initiated; settled asynchronously

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Refund::query()->first()->status)->toBe(RefundStatus::Approved)
        ->and($order->payments()->where('type', 'refund')->where('amount_minor', -1200)->count())->toBe(1);
});

it('processes a partial refund and marks the order partially_refunded', function () {
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-PAID-2');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 500,
        'reason' => 'partial refund',
    ], ['Idempotency-Key' => 'rf-partial'])->assertStatus(201);

    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and($order->payments()->where('type', 'refund')->where('amount_minor', -500)->count())->toBe(1);
});

it('rejects a refund exceeding the net captured amount (409)', function () {
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-PAID-3');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 2000,
        'reason' => 'too much',
    ], ['Idempotency-Key' => 'rf-over'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'order_not_refundable');
});

it('rejects a refund on a non-paid order (409)', function () {
    $admin = makeSuperAdmin();
    $order = Order::query()->create([
        'reference' => 'SH-AUTH-RF', 'payer_user_id' => makeUser()->getKey(),
        'purpose' => 'sub_main', 'amount_minor' => 1200, 'currency' => 'AZN',
        'status' => OrderStatus::Authorising, 'bank_order_id' => 'KB-AUTH-RF',
    ]);

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 1200,
        'reason' => 'not paid yet',
    ], ['Idempotency-Key' => 'rf-np'])->assertStatus(409);
});

it('forbids non-super-admins from issuing refunds (403)', function () {
    $tech = makeTechnicalAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-PAID-4');

    $this->actingAs($tech, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 1200,
        'reason' => 'should be denied',
    ], ['Idempotency-Key' => 'rf-tech'])->assertStatus(403);
});

it('lists refunds for any admin inside a {data, page} envelope', function () {
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-PAID-5');
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 1200, 'reason' => 'refund',
    ], ['Idempotency-Key' => 'rf-list'])->assertStatus(201);

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/refunds')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'order_id', 'amount_minor', 'status']], 'page']);
});
