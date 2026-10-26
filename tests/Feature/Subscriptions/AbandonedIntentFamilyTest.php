<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Actions\SweepAbandonedSubscriptionIntents;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Http;

/*
| O8 — when a never-paying family head's complex row is swept, the unpaid rows it granted to its family on that
| device go with it (no member keeps access the head lost); a head whose family has a paying member is left alone.
*/

beforeEach(function () {
    Http::fake();
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
    $this->cx = makeComplex('CX-O8', 'Kompleks O8');
    $this->device = makeActiveDevice('SER-O8-1', '+994700080001');
    $this->device->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
    $this->head = makeUser('+994500080001');
    app(ComplexMembershipService::class)->join($this->cx->id, $this->head);
    $this->actingAs($this->head, 'user')
        ->postJson("/v1/complexes/{$this->cx->id}/devices/{$this->device->id}/subscribe", [], ['Idempotency-Key' => 'o8-head'])
        ->assertCreated();
    Order::query()->update(['status' => 'expired']); // the head walked away from the checkout
});

function o8FamilyRow(Device $device, User $head, string $phone): DeviceUser
{
    $member = makeUser($phone);
    $link = FamilyLink::query()->create(['head_user_id' => $head->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    $row = DeviceUser::query()->create([
        'device_id' => $device->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active',
        'family_link_id' => $link->id, 'added_by_user_id' => $head->id,
    ]);
    app(SubscriptionService::class)->createPending($row, SubscriptionTier::Additional);

    return $row;
}

it('sweeps the unpaid family rows together with the head', function () {
    $memberRow = o8FamilyRow($this->device, $this->head, '+994500080002');
    $this->travel(25)->hours();

    expect(app(SweepAbandonedSubscriptionIntents::class)->handle())->toBe(1);

    expect($memberRow->fresh()->status->value)->toBe('revoked')
        ->and(Subscription::query()->where('device_user_id', $memberRow->id)->value('status'))->toBe(SubscriptionStatus::Cancelled)
        ->and(DeviceUser::query()->where('user_id', $this->head->id)->value('status')->value)->toBe('revoked');

    $audit = AuditLog::query()->where('action', 'subscription.abandoned_intent_swept')->sole();
    expect($audit->payload['family_device_user_ids'])->toBe([(int) $memberRow->id]);
});

it('leaves the head alone while a family member is paying', function () {
    $memberRow = o8FamilyRow($this->device, $this->head, '+994500080003');
    $memberSub = Subscription::query()->where('device_user_id', $memberRow->id)->firstOrFail();
    $order = Order::query()->create([
        'reference' => 'SH-O8-PAY', 'payer_user_id' => $memberRow->user_id, 'purpose' => 'sub_additional',
        'amount_minor' => 1200, 'currency' => 'AZN', 'status' => 'authorising', 'expires_at' => now()->addDays(3),
    ]);
    $order->items()->create([
        'item_type' => 'sub_additional', 'referenced_id' => $memberSub->id, 'description' => 'Abunə',
        'quantity' => 1, 'unit_amount_minor' => 1200, 'total_amount_minor' => 1200,
    ]);
    $this->travel(25)->hours();

    expect(app(SweepAbandonedSubscriptionIntents::class)->handle())->toBe(0);
    expect($memberRow->fresh()->status->value)->toBe('active')
        ->and(DeviceUser::query()->where('user_id', $this->head->id)->value('status')->value)->toBe('active');
});
