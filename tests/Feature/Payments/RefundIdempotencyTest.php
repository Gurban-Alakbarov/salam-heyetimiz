<?php

use App\Domain\Payments\Jobs\ApplyRefundJob;
use App\Domain\Payments\Models\Refund;
use Illuminate\Support\Facades\Bus;

/*
| O5 — the admin refund's Idempotency-Key is enforced per admin (a retry gets the first refund back, never a
| second), refunds still in flight count against what is refundable, and finance may refund (K3 decision).
*/

function refundAs($test, $admin, int $orderId, int $amount, string $key)
{
    return $test->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$orderId}/refund", [
        'amount_minor' => $amount,
        'reason' => 'customer request',
    ], ['Idempotency-Key' => $key]);
}

it('answers a retry with the same key with the first refund and refunds nothing again', function () {
    Bus::fake([ApplyRefundJob::class]);
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-O5-1');

    $first = refundAs($this, $admin, $order->id, 500, 'rf-o5-same')->assertStatus(201);
    $retry = refundAs($this, $admin, $order->id, 500, 'rf-o5-same')
        ->assertStatus(200)
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($retry->json('id'))->toBe($first->json('id'))
        ->and(Refund::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(ApplyRefundJob::class, 1);
});

it('refuses the same key for a different amount or order (409 idempotency_mismatch)', function () {
    Bus::fake([ApplyRefundJob::class]);
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser('+994501110501'), 1200, 'KB-O5-2');
    $other = makePaidOrder(makeUser('+994501110502'), 1200, 'KB-O5-3');

    refundAs($this, $admin, $order->id, 500, 'rf-o5-mismatch')->assertStatus(201);

    refundAs($this, $admin, $order->id, 600, 'rf-o5-mismatch')
        ->assertStatus(409)->assertJsonPath('error.code', 'idempotency_mismatch');
    refundAs($this, $admin, $other->id, 500, 'rf-o5-mismatch')
        ->assertStatus(409)->assertJsonPath('error.code', 'idempotency_mismatch');

    expect(Refund::query()->count())->toBe(1);
});

it('scopes the key to the admin — another admin with the same key starts its own request', function () {
    Bus::fake([ApplyRefundJob::class]);
    $a = makeSuperAdmin('o5-a@salamhayetimiz.az');
    $b = makeSuperAdmin('o5-b@salamhayetimiz.az');
    $order = makePaidOrder(makeUser(), 1200, 'KB-O5-4');

    refundAs($this, $a, $order->id, 300, 'shared-key')->assertStatus(201);
    refundAs($this, $b, $order->id, 300, 'shared-key')->assertStatus(201);

    expect(Refund::query()->count())->toBe(2);
});

it('counts refunds still in flight against the refundable amount', function () {
    Bus::fake([ApplyRefundJob::class]); // the first refund stays "requested"
    $admin = makeSuperAdmin();
    $order = makePaidOrder(makeUser(), 1200, 'KB-O5-5');

    refundAs($this, $admin, $order->id, 1000, 'rf-o5-a')->assertStatus(201);

    refundAs($this, $admin, $order->id, 400, 'rf-o5-b')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'order_not_refundable')
        ->assertJsonPath('error.details.net_minor', 200);

    refundAs($this, $admin, $order->id, 200, 'rf-o5-c')->assertStatus(201);
});

it('lets finance refund (refunds.create) but not an operator', function () {
    seedRbac();
    $order = makePaidOrder(makeUser(), 1200, 'KB-O5-6');

    refundAs($this, makeAdminRole('finance'), $order->id, 100, 'rf-o5-fin')->assertStatus(201);
    refundAs($this, makeAdminRole('operator'), $order->id, 100, 'rf-o5-op')->assertStatus(403);
});
