<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPeriod;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
| IMPLEMENTATION_PLAN B8 — family links: relation (family_links) / access (device_users.family_link_id) /
| billing (the member's own 12 AZN / 30-day subscription) are independent layers. Head or member pays; a
| member never manages, invites or shares; removal cancels with no refund; re-add reopens.
*/

function b8User(string $phone, string $email): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => $email, 'email_verified_at' => now()])->save();

    return $u->refresh();
}

/** Head invites → token captured from the service → member accepts through the API. */
function b8InviteAndAccept(User $head, Device $device, User $member): array
{
    test()->actingAs($head, 'user')->postJson("/v1/devices/{$device->id}/invitations", [
        'first_name' => 'Fam', 'last_name' => 'Member', 'email' => $member->email,
    ])->assertCreated()->assertJsonPath('data.granted', false);

    $invitation = Invitation::query()->where('invitee_email', mb_strtolower($member->email))->latest('id')->firstOrFail();
    $token = '';
    Bus::assertDispatched(SendInvitationEmailJob::class, function (SendInvitationEmailJob $job) use ($invitation, &$token): bool {
        if ($job->invitationId === (int) $invitation->id) {
            $token = $job->token;
        }

        return true;
    });

    return test()->actingAs($member, 'user')->postJson("/v1/invites/{$token}/accept")->assertOk()->json('data');
}

function b8Order(User $payer, Subscription $sub, string $key)
{
    return test()->actingAs($payer, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional', 'items' => [['item_type' => 'sub_additional', 'referenced_id' => $sub->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => $key]);
}

beforeEach(function () {
    Http::fake();
    Bus::fake([SendInvitationEmailJob::class]);
    $this->head = b8User('+994500080001', 'head@example.test');
    $this->device = makeOwnedDevice($this->head, 'SER-B8-1', '+994700080001'); // private: owner = head
});

it('head invites → member accepts: active link, head-granted row, member\'s own 12 AZN / 30-day additional subscription', function () {
    $member = b8User('+994500080002', 'kid@example.test');
    $data = b8InviteAndAccept($this->head, $this->device, $member);

    expect($data['kind'])->toBe('family_member')->and($data['device']['id'])->toBe($this->device->id);
    $link = FamilyLink::query()->findOrFail($data['family_link_id']);
    expect($link->status)->toBe(FamilyLinkStatus::Active)->and((int) $link->head_user_id)->toBe($this->head->id);

    $row = DeviceUser::query()->where('device_id', $this->device->id)->where('user_id', $member->id)->firstOrFail();
    expect((int) $row->family_link_id)->toBe($link->id)->and((int) $row->added_by_user_id)->toBe($this->head->id)
        ->and($row->role)->toBe(DeviceUserRole::User);

    $sub = Subscription::query()->findOrFail($data['subscription_id']);
    expect($sub->tier)->toBe(SubscriptionTier::Additional)->and($sub->price_minor)->toBe(1200)->and($sub->term_days)->toBe(30)
        ->and($sub->status)->toBe(SubscriptionStatus::PendingPayment);

    // a second device for an existing member is granted directly (relation already accepted)
    $second = makeOwnedDevice($this->head, 'SER-B8-2', '+994700080002');
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$second->id}/invitations", ['first_name' => 'F', 'last_name' => 'M', 'email' => 'kid@example.test'])
        ->assertCreated()->assertJsonPath('data.granted', true)->assertJsonPath('data.device.subscription.price_minor', 1200);
    expect(FamilyLink::query()->count())->toBe(1)->and(Subscription::query()->count())->toBe(2);

    $this->actingAs($this->head, 'user')->getJson('/v1/family/members')->assertOk()
        ->assertJsonPath('data.0.user_id', $member->id)->assertJsonCount(2, 'data.0.devices');
});

it('payment: head or member pays; unrelated users and other members cannot; payer ≠ beneficiary is recorded', function () {
    $kid1 = b8User('+994500080011', 'k1@example.test');
    $kid2 = b8User('+994500080012', 'k2@example.test');
    $sub1 = Subscription::query()->findOrFail(b8InviteAndAccept($this->head, $this->device, $kid1)['subscription_id']);
    $sub2 = Subscription::query()->findOrFail(b8InviteAndAccept($this->head, $this->device, $kid2)['subscription_id']);

    b8Order(makeUser('+994500080013'), $sub1, 'o-x')->assertForbidden();   // unrelated
    b8Order($kid2, $sub1, 'o-k2')->assertForbidden();                     // another member
    b8Order($this->head, $sub1, 'o-h')->assertCreated()->assertJsonPath('amount_minor', 1200);
    b8Order($kid2, $sub2, 'o-self')->assertCreated();

    $order = Order::query()->where('payer_user_id', $this->head->id)->firstOrFail();
    $order->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
    \App\Domain\Payments\Events\OrderPaid::dispatch($order->fresh());
    expect($sub1->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and((int) $sub1->periods()->value('paid_by_user_id'))->toBe($this->head->id)
        ->and((int) $sub1->deviceUser->user_id)->toBe($kid1->id);
});

it('head + 5 members = 6 separate subscriptions; the head pays 5 × 12 = 60 AZN for the members (72 with own)', function () {
    $headRow = DeviceUser::query()->where('device_id', $this->device->id)->where('user_id', $this->head->id)->firstOrFail();
    $own = app(\App\Domain\Subscriptions\Services\SubscriptionService::class)->createPending($headRow, SubscriptionTier::Main);
    $total = $this->actingAs($this->head, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_main', 'items' => [['item_type' => 'sub_main', 'referenced_id' => $own->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => 'own'])->assertCreated()->json('amount_minor');

    $members = 0;
    for ($i = 1; $i <= 5; $i++) {
        $kid = b8User('+99450008010'.$i, "m{$i}@example.test");
        $sub = Subscription::query()->findOrFail(b8InviteAndAccept($this->head, $this->device, $kid)['subscription_id']);
        $members += b8Order($this->head, $sub, 'm-'.$i)->assertCreated()->json('amount_minor');
    }

    expect($members)->toBe(6000)->and($total + $members)->toBe(7200)->and(Subscription::query()->count())->toBe(6)
        ->and(FamilyLink::query()->where('status', 'active')->count())->toBe(5);
});

it('a member cannot invite, manage or share — and sees nothing of other members', function () {
    $kid = b8User('+994500080021', 'kid@example.test');
    $other = b8User('+994500080022', 'other@example.test');
    b8InviteAndAccept($this->head, $this->device, $kid);
    b8InviteAndAccept($this->head, $this->device, $other);

    $this->actingAs($kid, 'user')->postJson("/v1/devices/{$this->device->id}/invitations", ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.test'])->assertForbidden();
    $this->actingAs($kid, 'user')->getJson("/v1/devices/{$this->device->id}/invitations")->assertForbidden();
    $this->actingAs($kid, 'user')->getJson('/v1/family/members')->assertOk()->assertJsonPath('data', []);
    $this->actingAs($kid, 'user')->getJson('/v1/family/subscriptions')->assertOk()->assertJsonPath('data', []);
    $this->actingAs($kid, 'user')->deleteJson("/v1/family/members/{$other->id}")->assertNotFound();

    // BR-19: even with an active, paid subscription a family member cannot create a visitor link
    Subscription::query()->where('device_user_id', DeviceUser::query()->where('user_id', $kid->id)->value('id'))
        ->update(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
    $this->actingAs($kid, 'user')->postJson("/v1/devices/{$this->device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertForbidden()->assertJsonPath('error.code', 'family_member_cannot_share');

    // a legacy roster user (no family link) keeps the right to share
    [$legacyDevice, $owner] = openableWithRoster('+994500080023', 'SER-B8-L', '+994700080023');
    $legacy = makeUser('+994500080024');
    $row = app(RosterService::class)->addMember($legacyDevice, $legacy, DeviceUserRole::User, ActorKind::User, $owner->id);
    makeSubscription($row);
    $this->actingAs($legacy, 'user')->postJson("/v1/devices/{$legacyDevice->id}/visitor-links", ['access_type' => 'one_time'])->assertCreated();
});

it('removal: link removed, access revoked, subscription cancelled without refund, audited, history kept; head can no longer pay', function () {
    $kid = b8User('+994500080031', 'kid@example.test');
    $data = b8InviteAndAccept($this->head, $this->device, $kid);
    $sub = Subscription::query()->findOrFail($data['subscription_id']);
    $sub->forceFill(['status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30)])->save();
    SubscriptionPeriod::query()->create([
        'subscription_id' => $sub->id, 'order_id' => makePaidOrder($this->head, 1200, 'KB-B8-1')->id, 'kind' => 'initial',
        'period_start' => now(), 'period_end' => now()->addDays(30), 'amount_minor' => 1200, 'paid_by_user_id' => $this->head->id,
    ]);

    $this->actingAs($this->head, 'user')->deleteJson("/v1/family/members/{$kid->id}")
        ->assertOk()->assertJsonPath('data.revoked_rows', 1)->assertJsonPath('data.cancelled_subscriptions', 1);

    expect(FamilyLink::query()->findOrFail($data['family_link_id'])->status)->toBe(FamilyLinkStatus::Removed)
        ->and(DeviceUser::query()->where('user_id', $kid->id)->firstOrFail()->status)->toBe(DeviceUserStatus::Revoked)
        ->and($sub->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(SubscriptionPeriod::query()->where('subscription_id', $sub->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'subscription.cancelled_on_removal')->first()?->payload['refund'])->toBe('none')
        ->and(AuditLog::query()->where('action', 'family.member_removed')->count())->toBe(1)
        ->and(DeviceUser::query()->where('user_id', $this->head->id)->firstOrFail()->status)->toBe(DeviceUserStatus::Active); // head untouched

    b8Order($this->head, $sub, 'after-remove')->assertForbidden();
    $this->actingAs($this->head, 'user')->deleteJson("/v1/family/members/{$kid->id}")->assertNotFound();
});

it('re-add: new link, same roster row reactivated, cancelled subscription reopened for payment, periods kept', function () {
    $kid = b8User('+994500080041', 'kid@example.test');
    $first = b8InviteAndAccept($this->head, $this->device, $kid);
    $sub = Subscription::query()->findOrFail($first['subscription_id']);
    SubscriptionPeriod::query()->create([
        'subscription_id' => $sub->id, 'order_id' => makePaidOrder($this->head, 1200, 'KB-B8-2')->id, 'kind' => 'initial',
        'period_start' => now()->subDays(40), 'period_end' => now()->subDays(10), 'amount_minor' => 1200, 'paid_by_user_id' => $this->head->id,
    ]);
    $this->actingAs($this->head, 'user')->deleteJson("/v1/family/members/{$kid->id}")->assertOk();

    $again = b8InviteAndAccept($this->head, $this->device, $kid);

    expect($again['family_link_id'])->not->toBe($first['family_link_id'])
        ->and($again['subscription_id'])->toBe($sub->id)
        ->and($sub->refresh()->status)->toBe(SubscriptionStatus::PendingPayment)
        ->and($sub->price_minor)->toBe(1200)
        ->and(SubscriptionPeriod::query()->where('subscription_id', $sub->id)->count())->toBe(1)
        ->and(DeviceUser::query()->where('user_id', $kid->id)->count())->toBe(1)
        ->and((int) DeviceUser::query()->where('user_id', $kid->id)->value('family_link_id'))->toBe($again['family_link_id'])
        ->and(FamilyLink::query()->where('member_user_id', $kid->id)->count())->toBe(2); // removed one kept as history
});

it('complex mode: a resident heads a family; the member gets no complex membership and cannot browse or subscribe', function () {
    $cx = makeComplex('CX-B8', 'Kompleks B8');
    $shared = makeActiveDevice('SER-B8-C', '+994700080051');
    $shared->forceFill(['ownership_mode' => 'complex', 'complex_id' => $cx->id, 'owner_user_id' => null])->save();
    $other = makeActiveDevice('SER-B8-C2', '+994700080052');
    $other->forceFill(['ownership_mode' => 'complex', 'complex_id' => $cx->id, 'owner_user_id' => null])->save();

    $resident = b8User('+994500080051', 'res@example.test');
    app(ComplexMembershipService::class)->join($cx->id, $resident);
    // a resident sees the shared device but is not a family head there until holding their own row
    $this->actingAs($resident, 'user')->postJson("/v1/devices/{$shared->id}/invitations", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'kid@example.test'])->assertForbidden();
    app(RosterService::class)->addMember($shared->refresh(), $resident, DeviceUserRole::User, ActorKind::User, $resident->id);

    $kid = b8User('+994500080052', 'kid@example.test');
    $data = b8InviteAndAccept($resident, $shared->refresh(), $kid);
    expect($data['device']['id'])->toBe($shared->id)
        ->and(app(ComplexMembershipService::class)->isMember($cx->id, $kid->id))->toBeFalse();

    $this->actingAs($kid, 'user')->getJson("/v1/complexes/{$cx->id}/devices")->assertNotFound();
    $this->actingAs($kid, 'user')->postJson("/v1/complexes/{$cx->id}/devices/{$other->id}/subscribe", [], ['Idempotency-Key' => 'k'])->assertNotFound();
    // the member cannot head a family on the shared device either
    $this->actingAs($kid, 'user')->postJson("/v1/devices/{$shared->id}/invitations", ['first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.test'])->assertForbidden();

    // head (resident) pays the member's complex subscription
    b8Order($resident, Subscription::query()->findOrFail($data['subscription_id']), 'cx-pay')->assertCreated()->assertJsonPath('amount_minor', 1200);
});

it('rejects self-invites, members already on the device and the head\'s own email', function () {
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->device->id}/invitations", ['first_name' => 'H', 'last_name' => 'H', 'email' => 'HEAD@example.test'])
        ->assertStatus(422)->assertJsonPath('error.code', 'invitation_invalid_target');

    $onDevice = b8User('+994500080061', 'there@example.test');
    app(RosterService::class)->addMember($this->device, $onDevice, DeviceUserRole::User, ActorKind::User, $this->head->id);
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->device->id}/invitations", ['first_name' => 'T', 'last_name' => 'T', 'email' => 'there@example.test'])
        ->assertStatus(409)->assertJsonPath('error.code', 'invitation_already_has_access');
});
