<?php

use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
| IMPLEMENTATION_PLAN B17 — the exact backend sequence the mobile family screens rely on, in COMPLEX mode
| (no backend change in B17; this pins the B8 contracts the app parses):
|   * which devices the caller heads is learnt from GET /v1/devices/{id}/invitations: 200 = head, 403 = not;
|   * Komendant → resident (joins, subscribes, pays) → the RESIDENT invites a family member for that device;
|   * the member accepts → family link + head-granted row + the member's own pending `additional`
|     subscription, no complex membership; member or head may pay, anyone else is refused; a cancelled
|     checkout keeps `pending_payment`; once paid the member is whitelisted on the complex device.
*/

function b17Verified(string $phone, string $email): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => $email, 'email_verified_at' => now()])->save();

    return $u->refresh();
}

function b17Token(string $email): string
{
    $invitation = Invitation::query()->where('invitee_email', mb_strtolower($email))->latest('id')->firstOrFail();
    $token = '';
    Bus::assertDispatched(SendInvitationEmailJob::class, function (SendInvitationEmailJob $job) use ($invitation, &$token): bool {
        if ($job->invitationId === (int) $invitation->id) {
            $token = $job->token;
        }

        return true;
    });

    return $token;
}

function b17Fake(Order $order, string $action): void
{
    test()->post(URL::temporarySignedRoute('fakeCheckoutAction', now()->addMinutes(30), ['reference' => $order->reference, 'action' => $action], false))
        ->assertRedirect();
}

function b17PayOrder(User $payer, int $subscriptionId, string $key)
{
    return test()->actingAs($payer, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional',
        'items' => [['item_type' => 'sub_additional', 'referenced_id' => $subscriptionId, 'quantity' => 1]],
    ], ['Idempotency-Key' => $key]);
}

beforeEach(function () {
    Http::fake();
    Bus::fake([SendInvitationEmailJob::class]);
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
    $this->cx = makeComplex('CX-B17', 'Kompleks B17');
    $this->manager = makeAdminRole('complex_manager', $this->cx->id);
    $d = makeActiveDevice('SER-B17-1', '+994700170001');
    $d->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
    $this->gate = $d->refresh();

    // Komendant invites the resident; the resident accepts, subscribes and pays (B16).
    $this->head = b17Verified('+994500170001', 'head@example.test');
    ['token' => $t] = app(InvitationService::class)->createComplexResident($this->cx->id, $this->manager, 'head@example.test', 'Rəşad', 'Sakin');
    $this->actingAs($this->head, 'user')->postJson("/v1/invites/{$t}/accept")->assertOk();
    $orderId = $this->actingAs($this->head, 'user')
        ->postJson("/v1/complexes/{$this->cx->id}/devices/{$this->gate->id}/subscribe", [], ['Idempotency-Key' => 'b17-head'])->json('id');
    b17Fake(Order::query()->findOrFail($orderId), 'pay');
});

it('head is recognised by the invitations probe (200); the invite is listed as pending; non-heads get 403', function () {
    $this->actingAs($this->head, 'user')->getJson("/v1/devices/{$this->gate->id}/invitations")->assertOk()->assertExactJson(['data' => []]);

    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Leyla', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertCreated()->assertJsonPath('data.granted', false)->assertJsonPath('data.invitation.status', 'pending');

    $list = $this->actingAs($this->head, 'user')->getJson("/v1/devices/{$this->gate->id}/invitations")->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0])->toMatchArray(['device_id' => $this->gate->id, 'email' => 'member@example.test', 'status' => 'pending']);

    // duplicate live invitation → 409
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Leyla', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertStatus(409)->assertJsonPath('error.code', 'invitation_already_pending');

    // another resident of the complex who has no roster row on the device → 403 (not a head there)
    $other = b17Verified('+994500170009', 'other@example.test');
    app(ComplexMembershipService::class)->join($this->cx->id, $other);
    $this->actingAs($other, 'user')->getJson("/v1/devices/{$this->gate->id}/invitations")->assertForbidden();
});

it('member accepts → link + head-granted row + own pending additional; no complex membership; wrong email / reuse refused', function () {
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Leyla', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertCreated();
    $token = b17Token('member@example.test');

    $this->actingAs(b17Verified('+994500170002', 'wrong@example.test'), 'user')
        ->postJson("/v1/invites/{$token}/accept")->assertStatus(403)->assertJsonPath('errors.code', 'invitation_email_mismatch');
    expect(FamilyLink::query()->count())->toBe(0);

    $member = b17Verified('+994500170003', 'member@example.test');
    $accepted = $this->actingAs($member, 'user')->postJson("/v1/invites/{$token}/accept")->assertOk()->json('data');

    $link = FamilyLink::query()->firstOrFail();
    expect([(int) $link->head_user_id, (int) $link->member_user_id, $link->status->value])->toBe([$this->head->id, $member->id, 'active'])
        ->and($accepted['family_link_id'])->toBe((int) $link->id);
    $row = DeviceUser::query()->where('user_id', $member->id)->firstOrFail();
    expect([(int) $row->device_id, (int) $row->family_link_id, (int) $row->added_by_user_id])->toBe([$this->gate->id, (int) $link->id, $this->head->id]);
    $sub = Subscription::query()->where('device_user_id', $row->id)->firstOrFail();
    expect([$sub->id, $sub->tier->value, $sub->status->value, (int) $sub->price_minor])->toBe([$accepted['subscription_id'], 'additional', 'pending_payment', 1200])
        ->and(app(ComplexMembershipService::class)->isMember($this->cx->id, $member->id))->toBeFalse();

    // the member is not a head on the device (probe → 403) and the link can never be reused
    $this->actingAs($member, 'user')->getJson("/v1/devices/{$this->gate->id}/invitations")->assertForbidden();
    $this->actingAs($member, 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(410);

    // the head's family view: the member, the granted device and its pending subscription
    $members = $this->actingAs($this->head, 'user')->getJson('/v1/family/members')->assertOk()->json('data');
    expect($members)->toHaveCount(1)
        ->and($members[0]['user_id'])->toBe($member->id)
        ->and($members[0]['devices'][0])->toMatchArray(['device_id' => $this->gate->id])
        ->and($members[0]['devices'][0]['subscription'])->toMatchArray(['id' => $sub->id, 'status' => 'pending_payment', 'tier' => 'additional']);

    // inviting an already-active member to the same device is refused (duplicate relationship / access)
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Leyla', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertStatus(409)->assertJsonPath('error.code', 'invitation_already_has_access');
});

it('expired and revoked family invitations are refused', function () {
    foreach (['exp@example.test', 'rev@example.test'] as $email) {
        $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
            'first_name' => 'F', 'last_name' => 'M', 'email' => $email,
        ])->assertCreated();
    }
    $expired = b17Token('exp@example.test');
    $revoked = b17Token('rev@example.test');
    Invitation::query()->where('invitee_email', 'exp@example.test')->update(['expires_at' => now()->subMinute()]);
    $revId = (int) Invitation::query()->where('invitee_email', 'rev@example.test')->value('id');
    $this->actingAs($this->head, 'user')->postJson("/v1/family/invitations/{$revId}/revoke")->assertOk()->assertJsonPath('data.status', 'cancelled');

    $statuses = collect($this->actingAs($this->head, 'user')->getJson("/v1/devices/{$this->gate->id}/invitations")->json('data'))->pluck('status', 'email');
    expect($statuses->all())->toMatchArray(['exp@example.test' => 'expired', 'rev@example.test' => 'cancelled']);

    $this->getJson("/v1/invites/{$expired}")->assertStatus(410);
    $this->actingAs(b17Verified('+994500170004', 'rev@example.test'), 'user')->postJson("/v1/invites/{$revoked}/accept")->assertStatus(410);
    expect(FamilyLink::query()->count())->toBe(0);
});

it('payment: outsider 403; cancelled checkout keeps pending; head pays → active + member whitelisted; member can pay too', function () {
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Leyla', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertCreated();
    $member = b17Verified('+994500170005', 'member@example.test');
    $subId = $this->actingAs($member, 'user')->postJson('/v1/invites/'.b17Token('member@example.test').'/accept')->json('data.subscription_id');

    b17PayOrder(b17Verified('+994500170006', 'outsider@example.test'), $subId, 'b17-x')->assertForbidden();

    $first = b17PayOrder($this->head, $subId, 'b17-head-1')->assertCreated()->json('id');
    b17Fake(Order::query()->findOrFail($first), 'cancel');
    expect(Subscription::query()->findOrFail($subId)->status->value)->toBe('pending_payment');

    $second = b17PayOrder($this->head, $subId, 'b17-head-2')->assertCreated()->json('id');
    b17Fake(Order::query()->findOrFail($second), 'pay');
    $sub = Subscription::query()->findOrFail($subId);
    expect($sub->status->value)->toBe('active')
        ->and((int) Order::query()->findOrFail($second)->payer_user_id)->toBe($this->head->id)
        ->and((int) $sub->deviceUser->user_id)->toBe($member->id);
    $last = WhitelistChange::query()->where('device_id', $this->gate->id)->where('phone', $member->phone)->orderByDesc('seq')->first();
    expect($last?->action->value)->toBe('add');

    // the member may pay for their own subscription as well (a fresh member, self-pay)
    $this->actingAs($this->head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Kamal', 'last_name' => 'Üzv', 'email' => 'self@example.test',
    ])->assertCreated();
    $self = b17Verified('+994500170007', 'self@example.test');
    $selfSub = $this->actingAs($self, 'user')->postJson('/v1/invites/'.b17Token('self@example.test').'/accept')->json('data.subscription_id');
    b17PayOrder($self, $selfSub, 'b17-self')->assertCreated()->assertJsonPath('amount_minor', 1200);
});
