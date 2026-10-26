<?php

use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Services\PaymentVerifierService;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use Illuminate\Support\Facades\Http;

/*
| O2 — whoever may PAY for a subscription may renew it: the family head renews a member's ended subscription
| (POST /v1/subscriptions/{id}/renew), pays as the payer, and the member's access is back. Strangers and a head
| whose family link has ended still get 404.
*/

beforeEach(function () {
    Http::fake();
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
    $cx = makeComplex('CX-O2', 'Kompleks O2');
    $this->gate = makeActiveDevice('SER-O2-1', '+994700220201');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $cx->id, 'owner_user_id' => null])->save();

    $this->head = makeUser('+994505220201');
    app(ComplexMembershipService::class)->join($cx->id, $this->head);
    makeSubscription(makeDeviceUser($this->head, $this->gate, 'user'), ['ends_at' => now()->addDays(20)]);

    $this->member = makeUser('+994505220202');
    $this->link = FamilyLink::query()->create(['head_user_id' => $this->head->id, 'member_user_id' => $this->member->id, 'status' => 'active', 'linked_at' => now()]);
    $row = DeviceUser::query()->create(['device_id' => $this->gate->id, 'user_id' => $this->member->id, 'role' => 'user', 'status' => 'active', 'family_link_id' => $this->link->id, 'added_by_user_id' => $this->head->id]);
    $this->memberSub = makeSubscription($row, ['tier' => SubscriptionTier::Additional, 'term_days' => 30, 'status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDays(3)]);
});

it('the family head renews a member\'s ended subscription and the member can open again', function () {
    $orderId = $this->withHeaders(bearer(userAccessToken($this->head)))
        ->postJson("/v1/subscriptions/{$this->memberSub->id}/renew", [], ['Idempotency-Key' => 'o2-renew'])
        ->assertOk()->json('id');

    $order = Order::query()->findOrFail($orderId);
    expect((int) $order->payer_user_id)->toBe($this->head->id);

    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Approved);
    app(PaymentVerifierService::class)->verifyAndApply($order);

    $sub = $this->memberSub->fresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->ends_at->isFuture())->toBeTrue()
        ->and((int) $sub->periods()->latest('id')->value('paid_by_user_id'))->toBe($this->head->id);
});

it('a stranger, or a head whose link has ended, still cannot renew it', function () {
    $this->withHeaders(bearer(userAccessToken(makeUser('+994505220299'))))
        ->postJson("/v1/subscriptions/{$this->memberSub->id}/renew", [], ['Idempotency-Key' => 'o2-x'])->assertNotFound();

    $this->link->forceFill(['status' => 'removed', 'removed_at' => now()])->save();
    $this->withHeaders(bearer(userAccessToken($this->head)))
        ->postJson("/v1/subscriptions/{$this->memberSub->id}/renew", [], ['Idempotency-Key' => 'o2-y'])->assertNotFound();
});

it('the member still renews their own subscription', function () {
    $this->withHeaders(bearer(userAccessToken($this->member)))
        ->postJson("/v1/subscriptions/{$this->memberSub->id}/renew", [], ['Idempotency-Key' => 'o2-self'])->assertOk();
});
