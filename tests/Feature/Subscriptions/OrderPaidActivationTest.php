<?php

use App\Domain\Payments\Events\OrderPaid;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Events\SubscriptionActivated;
use App\Domain\Subscriptions\Events\SubscriptionRenewed;
use Illuminate\Support\Facades\Event;

/*
| The "subscription activation trigger": OrderPaid → activate (first payment) or renew (subsequent).
*/

function makePaidSubOrder($payer, $sub, string $bankOrderId)
{
    $order = makePaidOrder($payer, 1200, $bankOrderId);
    $order->items()->create([
        'item_type' => 'sub_main',
        'referenced_id' => $sub->id,
        'description' => 'Main subscription',
        'quantity' => 1,
        'unit_amount_minor' => 1200,
        'total_amount_minor' => 1200,
    ]);

    return $order;
}

it('activates a pending subscription when its order is paid', function () {
    Event::fake([SubscriptionActivated::class]);

    $owner = makeUser('+994500000060');
    $device = makeActiveDevice('SER-P1', '+994700000060');
    $du = makeDeviceUser($owner, $device);
    $sub = makeSubscription($du, ['status' => SubscriptionStatus::PendingPayment, 'ends_at' => now()]);

    $order = makePaidSubOrder($owner, $sub, 'KB-PAID-ACT');

    OrderPaid::dispatch($order);

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->ends_at->greaterThan(now()->addDays(360)))->toBeTrue()
        ->and($sub->periods()->where('kind', SubscriptionPeriodKind::Initial->value)->count())->toBe(1);

    Event::assertDispatched(SubscriptionActivated::class);
});

it('renews an already-active subscription when a further order is paid', function () {
    Event::fake([SubscriptionRenewed::class]);

    $owner = makeUser('+994500000061');
    $device = makeActiveDevice('SER-P2', '+994700000061');
    $du = makeDeviceUser($owner, $device);
    $sub = makeSubscription($du, ['status' => SubscriptionStatus::Active, 'ends_at' => now()->addDays(10)]);
    $oldEndsAt = $sub->ends_at->copy();

    $order = makePaidSubOrder($owner, $sub, 'KB-PAID-REN');

    OrderPaid::dispatch($order);

    $sub->refresh();
    // Renewed within term: new ends_at = old ends_at + 365 days.
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and((int) round($oldEndsAt->diffInDays($sub->ends_at)))->toBe(365)
        ->and($sub->periods()->where('kind', SubscriptionPeriodKind::Renewal->value)->count())->toBe(1);

    Event::assertDispatched(SubscriptionRenewed::class);
});
