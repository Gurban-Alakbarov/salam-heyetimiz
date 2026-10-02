<?php

use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\ExpirySweep;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
| IMPLEMENTATION_PLAN B2 — 12 AZN / 30 days per subscription (resident AND each family member), payer ≠
| beneficiary ledger, legacy comps untouched, reopen after cancel, sale price never a subscription price.
*/

function subOrder(\App\Domain\Users\Models\User $payer, Subscription $sub, string $key, string $type = 'sub_main'): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($payer, 'user')->postJson('/v1/orders', [
        'purpose' => $type,
        'items' => [['item_type' => $type, 'referenced_id' => $sub->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => $key]);
}

function settle(Order $order, Subscription $sub): void
{
    $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
    OrderPaid::dispatch($order->fresh());
}

it('creates resident (main) and family (additional) subscriptions at 12 AZN / 30 days each', function () {
    $device = makeActiveDevice('SER-B2-1', '+994700020001');
    $main = app(SubscriptionService::class)->createPending(makeDeviceUser(makeUser('+994500020001'), $device), SubscriptionTier::Main);
    $family = app(SubscriptionService::class)->createPending(makeDeviceUser(makeUser('+994500020002'), $device, 'user'), SubscriptionTier::Additional);

    foreach ([$main, $family] as $sub) {
        expect($sub->status)->toBe(SubscriptionStatus::PendingPayment)
            ->and($sub->price_minor)->toBe(1200)
            ->and($sub->term_days)->toBe(30);
    }
});

it('head + 5 family members = 6 separate 12 AZN subscriptions; the head may pay a member (payer ≠ beneficiary)', function () {
    $device = makeActiveDevice('SER-B2-2', '+994700020002');
    $head = makeUser('+994500020010');
    $headSub = app(SubscriptionService::class)->createPending(makeDeviceUser($head, $device), SubscriptionTier::Main);

    $members = [];
    for ($i = 1; $i <= 5; $i++) {
        $member = makeUser('+99450002002'.$i);
        $members[] = [$member, app(SubscriptionService::class)->createPending(makeDeviceUser($member, $device, 'user'), SubscriptionTier::Additional)];
    }

    // Head pays own + member #1..#4; member #5 pays for themselves.
    subOrder($head, $headSub, 'b2-head')->assertCreated()->assertJsonPath('amount_minor', 1200);
    foreach ($members as $n => [$member, $sub]) {
        $payer = $n < 4 ? $head : $member;
        subOrder($payer, $sub, 'b2-fam-'.$n, 'sub_additional')->assertCreated()->assertJsonPath('amount_minor', 1200);
    }

    expect(Subscription::query()->count())->toBe(6)
        ->and((int) Order::query()->sum('amount_minor'))->toBe(6 * 1200); // 72 AZN / month

    // Settle a head-paid member order: beneficiary = member, payer recorded on the period ledger.
    [$member1, $sub1] = $members[0];
    $order = Order::query()->whereHas('items', fn ($q) => $q->where('referenced_id', $sub1->id))->firstOrFail();
    settle($order, $sub1);

    $sub1->refresh();
    $period = $sub1->periods()->firstOrFail();
    expect($sub1->status)->toBe(SubscriptionStatus::Active)
        ->and((int) $sub1->deviceUser->user_id)->toBe((int) $member1->id)   // beneficiary
        ->and((int) $period->paid_by_user_id)->toBe((int) $head->id)       // payer
        ->and((int) round($sub1->starts_at->diffInDays($sub1->ends_at)))->toBe(30);
});

it('never re-prices, converts or charges a legacy comp subscription', function () {
    $du = makeDeviceUser(makeUser('+994500020030'), makeActiveDevice('SER-B2-3', '+994700020003'));
    $comp = makeSubscription($du, ['price_minor' => 0, 'term_days' => 365, 'ends_at' => now()->addDays(200)]);
    $before = $comp->fresh()->only(['status', 'price_minor', 'term_days', 'ends_at', 'tier']);

    // createPending on a live row returns it untouched; reminders + expiry sweep don't touch price/term.
    app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main);
    app(ExpirySweep::class)->sendReminders();
    app(ExpirySweep::class)->expireBatch();
    Artisan::call('subscriptions:legacy-report', ['--list' => true]);

    expect($comp->fresh()->only(['status', 'price_minor', 'term_days', 'ends_at', 'tier']))->toEqual($before)
        ->and(Order::query()->count())->toBe(0)
        ->and(Artisan::output())->toContain('legacy_comp_total=1');
});

it('a user-initiated renewal of a comp is charged 12 AZN and renews for 30 days', function () {
    $me = makeUser('+994500020040');
    $du = makeDeviceUser($me, makeActiveDevice('SER-B2-4', '+994700020004'));
    $comp = makeSubscription($du, ['price_minor' => 0, 'term_days' => 365, 'ends_at' => now()->addDays(5)]);
    $endsBefore = $comp->ends_at->copy();

    $this->actingAs($me, 'user')
        ->postJson("/v1/subscriptions/{$comp->id}/renew", [], ['Idempotency-Key' => 'b2-renew-comp'])
        ->assertOk()->assertJsonPath('amount_minor', 1200);

    // Nothing changes on the comp row until the renewal is actually paid.
    expect($comp->fresh()->price_minor)->toBe(0)->and($comp->fresh()->term_days)->toBe(365);

    settle(Order::query()->firstOrFail(), $comp);
    $comp->refresh();
    expect($comp->price_minor)->toBe(1200)
        ->and($comp->term_days)->toBe(30)
        ->and((int) round($endsBefore->diffInDays($comp->ends_at)))->toBe(30);
});

it('never charges a 0 amount even when a stale snapshot says 0', function () {
    $me = makeUser('+994500020050');
    $comp = makeSubscription(makeDeviceUser($me, makeActiveDevice('SER-B2-5', '+994700020005')), ['price_minor' => 0]);

    subOrder($me, $comp, 'b2-zero', 'sub_renewal')->assertCreated()->assertJsonPath('amount_minor', 1200);
});

it('reopens a cancelled subscription for payment with a fresh snapshot, keeping the paid history', function () {
    $me = makeUser('+994500020060');
    $du = makeDeviceUser($me, makeActiveDevice('SER-B2-6', '+994700020006'));
    $sub = app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main);
    subOrder($me, $sub, 'b2-first')->assertCreated();
    settle(Order::query()->firstOrFail(), $sub);
    app(SubscriptionService::class)->cancel($sub->fresh(), 'removed');

    $reopened = app(SubscriptionService::class)->createPending($du, SubscriptionTier::Main);

    expect($reopened->id)->toBe($sub->id) // device_user_id is UNIQUE → same row
        ->and($reopened->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($reopened->price_minor)->toBe(1200)
        ->and($reopened->term_days)->toBe(30)
        ->and($reopened->periods()->count())->toBe(1); // history preserved
});

it('keeps the one-off device sale price out of every subscription amount', function () {
    $me = makeUser('+994500020070');
    $device = makeActiveDevice('SER-B2-7', '+994700020007');
    $device->forceFill(['sale_price_minor' => 25000, 'sale_recorded_at' => now()])->save();
    $sub = app(SubscriptionService::class)->createPending(makeDeviceUser($me, $device), SubscriptionTier::Main);

    subOrder($me, $sub, 'b2-sale')->assertCreated()->assertJsonPath('amount_minor', 1200);
    expect($sub->fresh()->price_minor)->toBe(1200);

    // The device_sale order line is unchanged (config default), never the per-device sale price.
    $line = app(\App\Domain\Payments\Support\OrderPricing::class)->unitPriceFor(new \App\Domain\Payments\DTOs\OrderItemData(
        itemType: \App\Domain\Payments\Enums\OrderItemType::Device, referencedId: $device->id, quantity: 1,
    ));
    expect($line)->toBe(13500)->and($line)->not->toBe(25000);
});

it('defaults every device to the private ownership mode (existing behaviour unchanged)', function () {
    $device = makeActiveDevice('SER-B2-8', '+994700020008')->fresh();

    expect($device->ownership_mode)->toBe(DeviceOwnershipMode::Private)
        ->and($device->sale_price_minor)->toBeNull()
        ->and(DB::table('devices')->where('ownership_mode', '!=', 'private')->count())->toBe(0);
});

it('monthly reminders fire only at D-7 and D-1', function () {
    Event::fake([SubscriptionExpiringSoon::class]);
    $sub = makeSubscription(makeDeviceUser(makeUser('+994500020080'), makeActiveDevice('SER-B2-9', '+994700020009')), [
        'term_days' => 30, 'ends_at' => now()->addDays(30),
    ]);

    app(ExpirySweep::class)->sendReminders();            // D-30 → disabled
    $this->travel(15)->days();
    app(ExpirySweep::class)->sendReminders();            // D-15 → disabled
    Event::assertNotDispatched(SubscriptionExpiringSoon::class);

    $this->travel(9)->days();                            // 6 days left
    app(ExpirySweep::class)->sendReminders();
    $this->travel(5)->days();                            // 1 day left
    app(ExpirySweep::class)->sendReminders();

    Event::assertDispatchedTimes(SubscriptionExpiringSoon::class, 2);
    expect($sub->fresh()->last_reminder_kind?->value)->toBe('d1');
});
