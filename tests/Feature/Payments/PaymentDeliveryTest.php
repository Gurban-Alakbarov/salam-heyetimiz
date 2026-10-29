<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\OrderReconciler;
use App\Domain\Payments\Services\OrderService;
use App\Domain\Payments\Services\PaymentVerifierService;
use App\Domain\Roster\Actions\RemoveComplexResident;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Http\Resources\OrderResource;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Http;

/*
| K2 + K3 — when the bank captures money we either deliver it or flag it for a refund; it is never silently lost.
|  K2: an order past its TTL is asked at the bank BEFORE it is expired; one we already expired is re-asked within
|      the late-capture window and a capture then pays it (late capture) — the subscription is activated.
|  K3: money for access that no longer exists (removed by the Komendant / deleted / device gone) never revives
|      the subscription; the order is flagged `refund_required` for finance (no automatic refund, by decision),
|      and such a subscription is not offered for payment in the first place.
*/

beforeEach(function () {
    Http::fake();
    $this->bank = app(FakeKapitalGateway::class);
    $this->bank->willReturnStatus(BankStatus::Pending);
    $this->cx = makeComplex('CX-PD', 'Kompleks PD');
    $this->gate = makeActiveDevice('SER-PD-1', '+994700640001');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
    $this->resident = makeUser('+994505640001');
    app(ComplexMembershipService::class)->join($this->cx->id, $this->resident);
});

/** Start the real complex subscribe flow; returns the in-flight order. */
function pdSubscribe(User $resident, int $complexId, Device $gate, string $key = 'pd-1'): Order
{
    $id = test()->withHeaders(bearer(userAccessToken($resident)))
        ->postJson("/v1/complexes/{$complexId}/devices/{$gate->id}/subscribe", [], ['Idempotency-Key' => $key])
        ->assertCreated()->json('id');

    return Order::query()->findOrFail($id);
}

function pdSubscription(User $resident, Device $gate): Subscription
{
    return Subscription::query()->whereHas('deviceUser', fn ($q) => $q->where('user_id', $resident->id)->where('device_id', $gate->id))->firstOrFail();
}

it('K2: an order past its TTL is asked at the bank before expiring — a capture is applied, not expired', function () {
    $order = pdSubscribe($this->resident, $this->cx->id, $this->gate);
    $this->travel(31)->minutes();
    $this->bank->willReturnStatus(BankStatus::Approved);

    $result = app(OrderReconciler::class)->reconcile();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($result['expired'])->toBe(0)
        ->and(pdSubscription($this->resident, $this->gate)->status)->toBe(SubscriptionStatus::Active);
});

it('K2: a capture after we expired the order pays it late and activates the subscription', function () {
    $order = pdSubscribe($this->resident, $this->cx->id, $this->gate);
    $this->travel(31)->minutes();

    $first = app(OrderReconciler::class)->reconcile(); // bank still PENDING → expired
    expect($order->fresh()->status)->toBe(OrderStatus::Expired)->and($first['expired'])->toBe(1);

    $this->travel(2)->hours();
    $this->bank->willReturnStatus(BankStatus::Approved); // the user finished on the bank page
    $second = app(OrderReconciler::class)->reconcile();

    $order->refresh();
    $sub = pdSubscription($this->resident, $this->gate);
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->metadata['late_capture']['previous_status'])->toBe('expired')
        ->and($second['late_captured'])->toBe(1)
        ->and($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->periods()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'order.late_capture')->count())->toBe(1);

    // a third run (and a duplicate callback) change nothing
    app(OrderReconciler::class)->reconcile();
    app(PaymentVerifierService::class)->verifyAndApply($order->fresh());
    expect($order->fresh()->payments()->count())->toBe(1)->and($sub->periods()->count())->toBe(1);
});

it('K2: an expired order the bank declines stays expired; one outside the window is no longer asked', function () {
    $declined = pdSubscribe($this->resident, $this->cx->id, $this->gate, 'pd-decl');
    $this->travel(31)->minutes();
    app(OrderReconciler::class)->reconcile();

    $this->bank->willReturnStatus(BankStatus::Declined);
    app(OrderReconciler::class)->reconcile();
    expect($declined->fresh()->status)->toBe(OrderStatus::Expired);

    $this->travel(73)->hours();
    $this->bank->willReturnStatus(BankStatus::Approved);
    $result = app(OrderReconciler::class)->reconcile();
    expect($declined->fresh()->status)->toBe(OrderStatus::Expired)->and($result['late_captured'])->toBe(0);
});

it('K3: money paid after the Komendant removed the resident never revives the subscription; the order is flagged', function () {
    $order = pdSubscribe($this->resident, $this->cx->id, $this->gate);
    $sub = pdSubscription($this->resident, $this->gate);

    $manager = makeAdminRole('complex_manager', $this->cx->id);
    app(RemoveComplexResident::class)->handle($this->cx->id, $this->resident, makeUser('+994505640099'), $manager);
    expect($sub->fresh()->status)->toBe(SubscriptionStatus::Cancelled);

    // the resident still had the bank page open and pays
    $this->bank->willReturnStatus(BankStatus::Approved);
    app(PaymentVerifierService::class)->verifyAndApply($order);

    $order->refresh();
    $sub->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($sub->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($sub->cancellation_reason)->toBe('removed_by_komendant')
        ->and($sub->periods()->count())->toBe(0)
        ->and($order->metadata['refund_required']['amount_minor'])->toBe(1500)
        ->and($order->metadata['refund_required']['lines'][0])->toMatchArray(['subscription_id' => $sub->id, 'reason' => 'access_removed'])
        ->and(AuditLog::query()->where('action', 'subscription.payment_undeliverable')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'order.refund_required')->count())->toBe(1);

    // finance worklist + the order payload
    $finance = makeAdminRole('finance');
    $row = collect($this->actingAs($finance, 'admin')->getJson('/admin/v1/orders?needs_refund=1')->assertOk()->json('data'))
        ->firstWhere('id', $order->id);
    expect($row)->not->toBeNull()->and($row['refund_required']['amount_minor'])->toBe(1500);
    expect(collect($this->actingAs($finance, 'admin')->getJson('/admin/v1/orders?needs_refund=1')->json('data'))->count())->toBe(1);

    // a replayed OrderPaid does not double the flag
    app(OrderService::class)->flagRefundRequired($order, $sub->id, 1500, 'access_removed');
    expect($order->fresh()->metadata['refund_required']['amount_minor'])->toBe(1500);

    // once finance refunds it, it leaves the worklist (and the payload no longer asks for it)
    $order->forceFill(['status' => OrderStatus::Refunded->value])->save();
    expect($this->actingAs($finance, 'admin')->getJson('/admin/v1/orders?needs_refund=1')->json('data'))->toBe([])
        ->and(OrderResource::make($order->fresh())->resolve()['refund_required'])->toBeNull();
});

it('K3: the same holds for a late capture after removal (K2 + K3 together)', function () {
    $order = pdSubscribe($this->resident, $this->cx->id, $this->gate);
    $this->travel(31)->minutes();
    app(OrderReconciler::class)->reconcile();
    expect($order->fresh()->status)->toBe(OrderStatus::Expired);

    app(RemoveComplexResident::class)->handle($this->cx->id, $this->resident, makeUser('+994505640098'), makeAdminRole('complex_manager', $this->cx->id));

    $this->bank->willReturnStatus(BankStatus::Approved);
    app(OrderReconciler::class)->reconcile();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->metadata['refund_required'])->not->toBeNull()
        ->and(pdSubscription($this->resident, $this->gate)->status)->toBe(SubscriptionStatus::Cancelled);
});

it('K3: a subscription without access is not offered for payment, whoever asks', function () {
    $this->bank->willReturnStatus(BankStatus::Approved);
    // private device: owner + member with an active subscription, then the admin removes the member
    $owner = makeUser('+994505640010');
    $device = makeOwnedDevice($owner, 'SER-PD-2', '+994700640002');
    $member = makeUser('+994505640011');
    $row = app(RosterService::class)->addMember($device, $member, DeviceUserRole::User, ActorKind::User, $owner->id);
    $sub = makeSubscription($row, ['status' => SubscriptionStatus::Active, 'ends_at' => now()->addDays(5)]);
    app(RosterService::class)->removeMember($device->refresh(), $member, ActorKind::Admin, null);

    $this->withHeaders(bearer(userAccessToken($member)))
        ->postJson("/v1/subscriptions/{$sub->id}/renew", [], ['Idempotency-Key' => 'pd-renew'])
        ->assertStatus(422);
    $this->withHeaders(bearer(userAccessToken($member)))->postJson('/v1/orders', [
        'purpose' => 'sub_renewal',
        'items' => [['item_type' => 'sub_renewal', 'referenced_id' => $sub->id, 'quantity' => 1]],
        'return_url' => 'salam://payment/return',
    ], ['Idempotency-Key' => 'pd-order'])->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

    expect(Order::query()->where('payer_user_id', $member->id)->count())->toBe(0);
});

it('a normal on-time payment is unchanged (activates, no flags)', function () {
    $order = pdSubscribe($this->resident, $this->cx->id, $this->gate);
    $this->bank->willReturnStatus(BankStatus::Approved);
    app(PaymentVerifierService::class)->verifyAndApply($order);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->metadata['refund_required'] ?? null)->toBeNull()
        ->and($order->metadata['late_capture'] ?? null)->toBeNull()
        ->and(pdSubscription($this->resident, $this->gate)->status)->toBe(SubscriptionStatus::Active);
});
