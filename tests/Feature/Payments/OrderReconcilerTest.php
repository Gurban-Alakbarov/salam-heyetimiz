<?php

use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\OrderReconciler;

it('reconciles a stale authorising order via getOrderStatus', function () {
    $order = Order::query()->create([
        'reference' => 'SH-REC-1', 'payer_user_id' => makeUser()->getKey(),
        'purpose' => 'sub_main', 'amount_minor' => 1200, 'currency' => 'AZN',
        'status' => OrderStatus::Authorising, 'bank_order_id' => 'KB-REC-1',
        'expires_at' => now()->addHour(),
    ]);
    // Backdate updated_at past the recheck threshold without touching timestamps.
    Order::query()->whereKey($order->id)->update(['updated_at' => now()->subHours(2)]);

    // FakeKapitalGateway default getOrderStatus = APPROVED.
    $result = app(OrderReconciler::class)->reconcile();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($result['resolved'])->toBeGreaterThanOrEqual(1);
});

it('expires in-flight orders past their TTL', function () {
    $order = Order::query()->create([
        'reference' => 'SH-REC-2', 'payer_user_id' => makeUser()->getKey(),
        'purpose' => 'sub_main', 'amount_minor' => 1200, 'currency' => 'AZN',
        'status' => OrderStatus::Pending, 'expires_at' => now()->subMinutes(5),
    ]);

    $result = app(OrderReconciler::class)->reconcile();

    expect($order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($result['expired'])->toBeGreaterThanOrEqual(1);
});
