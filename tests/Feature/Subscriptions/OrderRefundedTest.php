<?php

use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;

/*
| OrderRefunded / OrderPartiallyRefunded → subscription adjustment (§14.5.1), driven end-to-end
| through the admin refund endpoint (the Payments pipeline fires the events the listeners consume).
*/

function makeRefundableSubOrder($payer, $sub, int $amountMinor = 1200, string $bankOrderId = 'KB-SUB-RF')
{
    $order = makePaidOrder($payer, $amountMinor, $bankOrderId);
    $order->items()->create([
        'item_type' => 'sub_main',
        'referenced_id' => $sub->id,
        'description' => 'Main subscription',
        'quantity' => 1,
        'unit_amount_minor' => $amountMinor,
        'total_amount_minor' => $amountMinor,
    ]);

    return $order;
}

it('fully revokes the subscription on a full money refund', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994500000050');
    $device = makeActiveDevice('SER-RF1', '+994700000050');
    $du = makeDeviceUser($owner, $device);
    $sub = makeSubscription($du); // active, 1200, ends now+365

    $order = makeRefundableSubOrder($owner, $sub, 1200, 'KB-SUB-RF-FULL');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 1200,
        'reason' => 'full refund',
    ], ['Idempotency-Key' => 'sub-rf-full'])->assertStatus(201);

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Refunded)
        ->and($sub->periods()->where('kind', SubscriptionPeriodKind::Refund->value)->where('amount_minor', -1200)->count())->toBe(1);
});

it('reduces the term pro-rata on a partial money refund', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994500000051');
    $device = makeActiveDevice('SER-RF2', '+994700000051');
    $du = makeDeviceUser($owner, $device);
    $sub = makeSubscription($du); // active, price 1200 / term 365, ends now+365
    $originalEndsAt = $sub->ends_at->copy();

    $order = makeRefundableSubOrder($owner, $sub, 1200, 'KB-SUB-RF-PART');

    // Refund 400 qəpik → price_per_day=3 → remove 133 days; stays active.
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/orders/{$order->id}/refund", [
        'amount_minor' => 400,
        'reason' => 'partial refund',
    ], ['Idempotency-Key' => 'sub-rf-part'])->assertStatus(201);

    $sub->refresh();
    expect($sub->status)->toBe(SubscriptionStatus::Active)
        ->and($sub->ends_at->lessThan($originalEndsAt))->toBeTrue()
        ->and($sub->periods()->where('kind', SubscriptionPeriodKind::Refund->value)->where('amount_minor', -400)->count())->toBe(1);

    // 133 days removed (§14.5.1).
    expect((int) round(abs($originalEndsAt->diffInDays($sub->ends_at))))->toBe(133);
});
