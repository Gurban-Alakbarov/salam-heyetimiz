<?php

use Illuminate\Support\Str;

/*
| Payments/Refunds admin back-office polish: dashboard stats, enriched order detail + timeline, order search,
| refund history duration + raw.
*/

beforeEach(fn () => seedRbac());

it('serves dashboard payment statistics', function () {
    makePaidOrder(makeUser(), 1000, 'st-1');

    $stats = $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/payments/stats')
        ->assertOk()
        ->assertJsonStructure([
            'periods' => ['today', 'yesterday', 'this_week', 'this_month'],
            'totals' => ['paid', 'pending', 'authorising', 'failed', 'cancelled', 'refunded', 'partially_refunded', 'total_paid_minor', 'total_refunded_minor'],
            'rates' => ['success_rate', 'refund_rate'],
            'avg_payment_seconds', 'avg_refund_seconds',
        ])->json();

    expect($stats['totals']['paid'])->toBeGreaterThanOrEqual(1)
        ->and($stats['totals']['total_paid_minor'])->toBeGreaterThanOrEqual(1000);
});

it('enriches order detail with payment_detail + professional timeline + balances', function () {
    $order = makePaidOrder(makeUser(), 1000, 'pd-1');

    $d = $this->actingAs(makeSuperAdmin(), 'admin')->getJson("/admin/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonStructure([
            'bank_order_id', 'captured_minor', 'refunded_minor', 'refundable_minor',
            'payment_detail' => ['merchant_id', 'terminal_id', 'bank_order_id', 'idempotency_key', 'webhook_status', 'last_sync_at', 'rrn', 'approval_code'],
            'timeline' => [['at', 'event', 'description', 'actor', 'source', 'status', 'color']],
        ])->json();

    expect($d['bank_order_id'])->toBe('pd-1')->and($d['refundable_minor'])->toBe(1000);
});

it('searches orders by reference and bank_order_id, and the list carries balances + customer', function () {
    $order = makePaidOrder(makeUser('+994509990011'), 1000, 'SEARCHABLE-BANK');

    $byRef = $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/orders?q='.$order->reference)->assertOk()->json();
    expect(collect($byRef['data'])->pluck('id'))->toContain($order->id);

    $byBank = $this->actingAs(makeSuperAdmin('s2@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/orders?q=SEARCHABLE-BANK')->assertOk()->json();
    expect(collect($byBank['data'])->pluck('id'))->toContain($order->id)
        ->and($byBank['data'][0])->toHaveKeys(['bank_order_id', 'customer', 'refunded_minor', 'refundable_minor', 'updated_at']);
});

it('includes refund duration + raw response in the refund history', function () {
    $order = makePaidOrder(makeUser(), 1000, 'rf-hist');

    $this->actingAs(makeSuperAdmin(), 'admin')->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/admin/v1/orders/{$order->id}/refund", ['amount_minor' => 500, 'reason' => 'polish'])->assertCreated();

    $hist = $this->actingAs(makeSuperAdmin('s2@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/refunds')
        ->assertOk()->assertJsonStructure(['data' => [['duration_seconds', 'raw_response', 'bank_refund_id', 'order_reference']]])->json();

    expect($hist['data'][0]['bank_refund_id'])->not->toBeNull();
});
