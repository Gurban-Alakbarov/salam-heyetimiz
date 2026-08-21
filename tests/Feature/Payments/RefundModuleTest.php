<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\RefundStatus;
use Illuminate\Support\Str;

/*
| Phase 4 — Admin refund module. Refund executes synchronously via FakeKapitalGateway (bound in testing).
*/

beforeEach(fn () => seedRbac());

function refundReq($admin, int $orderId, int $amountMinor, string $reason = 'customer request'): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($admin, 'admin')
        ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/admin/v1/orders/{$orderId}/refund", ['amount_minor' => $amountMinor, 'reason' => $reason]);
}

it('processes a full refund: order refunded, refund approved + bank id + completed_at, audited', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-full');
    refundReq(makeSuperAdmin(), $order->id, 1000)->assertCreated();

    expect($order->refresh()->status)->toBe(OrderStatus::Refunded);
    $refund = $order->refunds()->first();
    expect($refund->status)->toBe(RefundStatus::Approved)
        ->and($refund->bank_refund_id)->not->toBeNull()
        ->and($refund->completed_at)->not->toBeNull();

    expect(AuditLog::query()->where('action', 'refund.requested')->count())->toBeGreaterThanOrEqual(1)
        ->and(AuditLog::query()->where('action', 'refund.completed')->count())->toBeGreaterThanOrEqual(1);
});

it('processes a partial refund (order partially_refunded)', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-part');
    refundReq(makeSuperAdmin(), $order->id, 400)->assertCreated();
    expect($order->refresh()->status)->toBe(OrderStatus::PartiallyRefunded);
});

it('allows multiple partial refunds within the remaining amount', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-multi');
    refundReq(makeSuperAdmin(), $order->id, 400)->assertCreated();
    refundReq(makeSuperAdmin('s2@salamhayetimiz.az'), $order->id, 600)->assertCreated();
    expect($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->refunds()->count())->toBe(2);
});

it('rejects an over-refund that exceeds the remaining amount', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-over');
    $res = refundReq(makeSuperAdmin(), $order->id, 1500);
    expect($res->status())->toBeIn([409, 422]);
    expect($order->refresh()->status)->toBe(OrderStatus::Paid);
});

it('prevents refunding more than captured across requests', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-dup');
    refundReq(makeSuperAdmin(), $order->id, 1000)->assertCreated();
    $res = refundReq(makeSuperAdmin('s2@salamhayetimiz.az'), $order->id, 1000);
    expect($res->status())->toBeIn([409, 422]); // nothing left to refund
});

it('enforces RBAC: finance can refund, operator/support cannot (backend)', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-rbac');
    refundReq(makeAdminRole('finance'), $order->id, 100)->assertCreated();

    $order2 = makePaidOrder(makeUser('+994509998877'), 1000, 'bp-rbac2');
    refundReq(makeAdminRole('operator'), $order2->id, 100)->assertStatus(403);
    refundReq(makeAdminRole('support'), $order2->id, 100)->assertStatus(403);
    expect($order2->refresh()->status)->toBe(OrderStatus::Paid);
});

it('maps a BirPay refund failure to a failed refund (order unchanged) + audit', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-fail');
    app(FakeKapitalGateway::class)->willDeclineRefund();

    refundReq(makeSuperAdmin(), $order->id, 500)->assertCreated(); // request accepted; execution fails

    $refund = $order->refunds()->first();
    expect($refund->status)->toBe(RefundStatus::Failed)
        ->and($refund->error_message)->not->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid);
    expect(AuditLog::query()->where('action', 'refund.failed')->count())->toBeGreaterThanOrEqual(1);
});

it('surfaces the refund in the order timeline, balances, and refund history', function () {
    $order = makePaidOrder(makeUser(), 1000, 'bp-tl');
    refundReq(makeSuperAdmin(), $order->id, 1000)->assertCreated();

    $detail = test()->actingAs(makeSuperAdmin('s3@salamhayetimiz.az'), 'admin')->getJson("/admin/v1/orders/{$order->id}")->json();
    expect(collect($detail['timeline'])->pluck('event'))->toContain('refund_requested')->toContain('refund_completed')
        ->and($detail['refunded_minor'])->toBe(1000)
        ->and($detail['refundable_minor'])->toBe(0);

    $hist = test()->actingAs(makeSuperAdmin('s4@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/refunds')
        ->assertOk()->assertJsonStructure(['data' => [['id', 'order_reference', 'bank_refund_id', 'status', 'completed_at', 'requested_by']]])->json();
    expect($hist['data'][0]['status'])->toBe('approved');
});
