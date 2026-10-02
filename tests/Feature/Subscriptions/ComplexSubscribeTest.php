<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Actions\SweepAbandonedSubscriptionIntents;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
| IMPLEMENTATION_PLAN B7 — a complex resident browses the shared devices, subscribes (own 12 AZN / 30 days),
| pays through the fake gateway and may then open. Payer rule enforced server-side; complex whitelist follows
| the paid subscription; unpaid intents are swept. Private devices keep their behaviour.
*/

function b7Device(int $complexId, string $serial, string $sim): Device
{
    $d = makeActiveDevice($serial, $sim);
    $d->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complexId, 'owner_user_id' => null, 'sale_price_minor' => 25000])->save();

    return $d->refresh();
}

function b7Subscribe(User $u, int $complexId, int $deviceId, string $key)
{
    return test()->actingAs($u, 'user')->postJson("/v1/complexes/{$complexId}/devices/{$deviceId}/subscribe", [], ['Idempotency-Key' => $key]);
}

function b7Fake(Order $order, string $action)
{
    return test()->post(URL::temporarySignedRoute('fakeCheckoutAction', now()->addMinutes(30), ['reference' => $order->reference, 'action' => $action], false));
}

/** B8: family rows must reference a real, active family link. */
function b7Link(User $head, User $member): int
{
    return (int) \App\Domain\Roster\Models\FamilyLink::query()->create([
        'head_user_id' => $head->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now(),
    ])->id;
}

function b7Whitelisted(Device $d, User $u): bool
{
    $last = WhitelistChange::query()->where('device_id', $d->id)->where('phone', $u->phone)->orderByDesc('seq')->first();

    return $last !== null && $last->action->value === 'add';
}

beforeEach(function () {
    Http::fake();
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
    $this->cx = makeComplex('CX-B7', 'Kompleks B7');
    $this->device = b7Device($this->cx->id, 'SER-B7-1', '+994700070001');
    $this->resident = makeUser('+994500070001');
    app(ComplexMembershipService::class)->join($this->cx->id, $this->resident);
});

it('lists only member complexes and their shared devices with the 12 AZN / 30-day price (never sale price)', function () {
    $other = makeComplex('CX-B7-O', 'Other');
    b7Device($other->id, 'SER-B7-2', '+994700070002');
    $as = $this->actingAs($this->resident, 'user');

    expect($as->getJson('/v1/complexes')->assertOk()->json('data'))->toBe([['id' => $this->cx->id, 'name' => 'Kompleks B7', 'address' => null]]);
    $devices = $as->getJson("/v1/complexes/{$this->cx->id}/devices")->assertOk()->json('data');
    expect($devices)->toHaveCount(1)
        ->and($devices[0])->toMatchArray(['id' => $this->device->id, 'subscription_price_minor' => 1200, 'subscription_term_days' => 30, 'my_subscription_status' => 'none'])
        ->and($devices[0])->not->toHaveKey('sale_price_minor');

    $as->getJson("/v1/complexes/{$other->id}")->assertNotFound();
    $as->getJson("/v1/complexes/{$other->id}/devices")->assertNotFound();
});

it('non-members cannot subscribe; members of another complex cannot reach this device', function () {
    b7Subscribe(makeUser('+994500070002'), $this->cx->id, $this->device->id, 'k-nm')->assertNotFound();

    $other = makeComplex('CX-B7-X', 'X');
    b7Subscribe($this->resident, $other->id, $this->device->id, 'k-x')->assertNotFound();
    expect(Order::query()->count())->toBe(0)->and(DeviceUser::query()->count())->toBe(0);

    // a private device in the same complex is not subscribable here
    $private = makeOwnedDevice(makeUser('+994500070003'), 'SER-B7-P', '+994700070003');
    $private->forceFill(['complex_id' => $this->cx->id])->save();
    b7Subscribe($this->resident, $this->cx->id, $private->id, 'k-p')->assertNotFound();
});

it('subscribe → fake paid → canOpen; idempotent; whitelist only after payment', function () {
    $res = b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-1')->assertCreated()
        ->assertJsonPath('amount_minor', 1200)->assertJsonPath('is_test', true);
    expect($res->json('bank_redirect_url'))->toContain('/v1/payments/fake-checkout/');
    $orderId = $res->json('id');

    // replay (same key) and a new key while in flight → the same order, one roster row, one subscription
    expect(b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-1')->json('id'))->toBe($orderId)
        ->and(b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-2')->json('id'))->toBe($orderId)
        ->and(DeviceUser::query()->count())->toBe(1)->and(Subscription::query()->count())->toBe(1);

    $sub = Subscription::query()->firstOrFail();
    expect($sub->status)->toBe(SubscriptionStatus::PendingPayment)->and($sub->tier)->toBe(SubscriptionTier::Main)
        ->and(app(SubscriptionStatusQuery::class)->for($this->resident->id, $this->device->id)->canOpen)->toBeFalse()
        ->and(b7Whitelisted($this->device, $this->resident))->toBeFalse(); // roster row alone → no call-to-open

    b7Fake(Order::query()->findOrFail($orderId), 'pay')->assertRedirect();

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and((int) $sub->periods()->value('paid_by_user_id'))->toBe($this->resident->id)
        ->and(app(SubscriptionStatusQuery::class)->for($this->resident->id, $this->device->id)->canOpen)->toBeTrue()
        ->and(b7Whitelisted($this->device, $this->resident))->toBeTrue();

    $this->actingAs($this->resident, 'user')->getJson("/v1/complexes/{$this->cx->id}/devices")->assertJsonPath('data.0.my_subscription_status', 'active');
    b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-3')->assertStatus(409);

    // expiry removes call-to-open again
    app(SubscriptionService::class)->expire($sub->refresh());
    expect(b7Whitelisted($this->device, $this->resident))->toBeFalse();
});

it('failed payment → retry on the same subscription with a new order', function () {
    $first = b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-f1')->assertCreated()->json('id');
    b7Fake(Order::query()->findOrFail($first), 'decline');
    expect(Order::query()->findOrFail($first)->status->value)->toBe('failed');

    $second = b7Subscribe($this->resident, $this->cx->id, $this->device->id, 'k-f2')->assertCreated()->json('id');
    expect($second)->not->toBe($first)->and(Subscription::query()->count())->toBe(1);

    b7Fake(Order::query()->findOrFail($second), 'pay');
    expect(Subscription::query()->firstOrFail()->status)->toBe(SubscriptionStatus::Active);
});

it('payer rule: beneficiary or family head only; never another resident or another family member', function () {
    $roster = app(RosterService::class);
    $head = $this->resident;
    $headRow = $roster->addMember($this->device, $head, DeviceUserRole::User, ActorKind::User, $head->id);
    $kids = [];
    foreach ([1, 2] as $i) {
        $kid = makeUser('+99450007010'.$i);
        $row = $roster->addMember($this->device, $kid, DeviceUserRole::User, ActorKind::User, $head->id, b7Link($head, $kid));
        $kids[] = [$kid, app(SubscriptionService::class)->createPending($row, SubscriptionTier::Additional)];
    }
    $neighbour = makeUser('+994500070110');
    app(ComplexMembershipService::class)->join($this->cx->id, $neighbour);

    $pay = fn (User $payer, Subscription $s, string $k) => $this->actingAs($payer, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional', 'items' => [['item_type' => 'sub_additional', 'referenced_id' => $s->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => $k]);

    [$kid1, $sub1] = $kids[0];
    [$kid2, $sub2] = $kids[1];
    $pay($neighbour, $sub1, 'p-n')->assertForbidden();   // another resident
    $pay($kid2, $sub1, 'p-k')->assertForbidden();        // another family member
    $pay($head, $sub1, 'p-h')->assertCreated()->assertJsonPath('amount_minor', 1200); // head pays member #1
    $pay($kid1, $sub1, 'p-k1')->assertStatus(422);       // member's own attempt while head's checkout is open
    $pay($kid2, $sub2, 'p-k2')->assertCreated();         // member #2 pays themself

    $headOrder = Order::query()->where('payer_user_id', $head->id)->firstOrFail();
    b7Fake($headOrder, 'pay');
    $period = $sub1->refresh()->periods()->firstOrFail();
    expect($sub1->status)->toBe(SubscriptionStatus::Active)
        ->and((int) $sub1->deviceUser->user_id)->toBe($kid1->id)       // beneficiary
        ->and((int) $period->paid_by_user_id)->toBe($head->id);       // payer

    // a head who left the device can no longer pay for that row
    $roster->removeMember($this->device, $head, ActorKind::Admin, null);
    $pay($head, $sub2, 'p-h2')->assertForbidden();
    expect($headRow->refresh()->status)->toBe(DeviceUserStatus::Revoked);
});

it('5 family members = 5 × 12 AZN paid by the head (+ the head\'s own 12 AZN)', function () {
    $roster = app(RosterService::class);
    $head = $this->resident;
    $orders = [b7Subscribe($head, $this->cx->id, $this->device->id, 'h-own')->assertCreated()->json('amount_minor')];
    for ($i = 1; $i <= 5; $i++) {
        $fam = makeUser('+99450007020'.$i);
        $row = $roster->addMember($this->device, $fam, DeviceUserRole::User, ActorKind::User, $head->id, b7Link($head, $fam));
        $sub = app(SubscriptionService::class)->createPending($row, SubscriptionTier::Additional);
        $orders[] = $this->actingAs($head, 'user')->postJson('/v1/orders', [
            'purpose' => 'sub_additional', 'items' => [['item_type' => 'sub_additional', 'referenced_id' => $sub->id, 'quantity' => 1]],
        ], ['Idempotency-Key' => 'h-fam-'.$i])->assertCreated()->json('amount_minor');
    }

    expect(array_sum(array_slice($orders, 1)))->toBe(5 * 1200)
        ->and(array_sum($orders))->toBe(6 * 1200)
        ->and(Subscription::query()->count())->toBe(6);
});

it('private devices keep the owner paying for members and the roster-based whitelist', function () {
    $owner = makeUser('+994500070301');
    $device = makeOwnedDevice($owner, 'SER-B7-PV', '+994700070301');
    $member = makeUser('+994500070302');
    $row = app(RosterService::class)->addMember($device, $member, DeviceUserRole::User, ActorKind::User, $owner->id);
    expect(b7Whitelisted($device, $member))->toBeTrue(); // unchanged: roster add → whitelist

    $sub = app(SubscriptionService::class)->createPending($row, SubscriptionTier::Additional);
    $this->actingAs($owner, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional', 'items' => [['item_type' => 'sub_additional', 'referenced_id' => $sub->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'pv-1'])->assertCreated();
    $this->actingAs(makeUser('+994500070303'), 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional', 'items' => [['item_type' => 'sub_additional', 'referenced_id' => $sub->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'pv-2'])->assertForbidden();
});

it('sweeps abandoned never-paid intents but keeps in-flight, paid and recent ones', function () {
    b7Subscribe($this->resident, $this->cx->id, $this->device->id, 's-1')->assertCreated();
    $sweep = app(SweepAbandonedSubscriptionIntents::class);

    $this->travel(25)->hours();
    expect($sweep->handle())->toBe(0); // order still in flight (not yet expired)

    Order::query()->update(['status' => 'expired']);
    expect($sweep->handle())->toBe(1);
    expect(Subscription::query()->firstOrFail()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(DeviceUser::query()->firstOrFail()->status)->toBe(DeviceUserStatus::Revoked)
        ->and(AuditLog::query()->where('action', 'subscription.abandoned_intent_swept')->count())->toBe(1);

    // coming back later just reactivates + reopens
    $id = b7Subscribe($this->resident, $this->cx->id, $this->device->id, 's-2')->assertCreated()->json('id');
    expect(DeviceUser::query()->count())->toBe(1)->and(Subscription::query()->firstOrFail()->status)->toBe(SubscriptionStatus::PendingPayment);
    b7Fake(Order::query()->findOrFail($id), 'pay');
    $this->travel(30)->hours();
    expect($sweep->handle())->toBe(0); // paid history → never swept
});
