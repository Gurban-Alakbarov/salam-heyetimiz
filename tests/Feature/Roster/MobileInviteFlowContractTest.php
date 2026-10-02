<?php

use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Adapters\FakeKapitalGateway;
use App\Domain\Payments\Enums\BankStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/*
| IMPLEMENTATION_PLAN B16 — the exact backend sequence and response shapes the mobile invitation flow relies on
| (no backend change in B16; this pins the B6 / B7 / B8 contracts the app parses):
|   register (invitation_token = pre-flight only) → verify-email WITHOUT the token → POST /v1/invites/{t}/accept
|   → complex membership ONLY (no device access, no subscription) → subscribe a chosen device (own `main`,
|   pending_payment) → fake pay → active. A family invitation (sent by the resident, never the Komendant) →
|   link + head-granted row + the member's own pending `additional`, listed under status=pending_payment.
*/

function b16Otp(string $email): string
{
    return (string) app(FakeEmailOtpTransport::class)->lastCodeFor(mb_strtolower($email));
}

function b16Verified(string $phone, string $email): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => $email, 'email_verified_at' => now()])->save();

    return $u->refresh();
}

function b16ComplexDevice(int $complexId, string $serial, string $sim): Device
{
    $d = makeActiveDevice($serial, $sim);
    $d->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complexId, 'owner_user_id' => null])->save();

    return $d->refresh();
}

function b16Pay(Order $order): void
{
    test()->post(URL::temporarySignedRoute('fakeCheckoutAction', now()->addMinutes(30), ['reference' => $order->reference, 'action' => 'pay'], false))
        ->assertRedirect();
}

beforeEach(function () {
    Http::fake();
    Bus::fake([SendInvitationEmailJob::class]);
    app(FakeKapitalGateway::class)->willReturnStatus(BankStatus::Pending);
    $this->cx = makeComplex('CX-B16', 'Kompleks B16');
    $this->manager = makeAdminRole('complex_manager', $this->cx->id);
    $this->gate = b16ComplexDevice($this->cx->id, 'SER-B16-1', '+994700160001');
    $this->invite = fn (string $email) => app(InvitationService::class)
        ->createComplexResident($this->cx->id, $this->manager, $email, 'Aysel', 'Məmmədova');
});

it('new user: register pre-flight → verify (no token) → accept → membership only; subscribe → pending → pay → active', function () {
    ['token' => $token] = ($this->invite)('aysel@example.test');
    $preview = $this->getJson("/v1/invites/{$token}")->assertOk()->json('data');
    expect($preview)->toMatchArray(['kind' => 'complex_resident', 'complex_name' => 'Kompleks B16', 'first_name' => 'Aysel', 'last_name' => 'Məmmədova'])
        ->and($preview['email_masked'])->toBe('a****@example.test');

    $body = ['first_name' => 'Aysel', 'last_name' => 'Məmmədova', 'phone' => '+994500160001', 'email' => 'aysel@example.test', 'invitation_token' => $token];
    $this->postJson('/v1/auth/register', $body)->assertStatus(202);
    $tokens = $this->postJson('/v1/auth/verify-email', [
        'email' => 'aysel@example.test', 'code' => b16Otp('aysel@example.test'),
        'device' => ['install_uuid' => (string) Str::uuid(), 'platform' => 'android', 'app_version' => '1.0.0'],
    ])->assertOk()->assertJsonMissingPath('data.invitation');
    expect($tokens->json('data.access_token'))->toBeString();

    $user = User::query()->where('email', 'aysel@example.test')->firstOrFail();
    $members = app(ComplexMembershipService::class);
    expect($members->isMember($this->cx->id, $user->id))->toBeFalse(); // registering alone never claims

    $as = $this->actingAs($user, 'user');
    $as->postJson("/v1/invites/{$token}/accept")->assertOk()
        ->assertJsonPath('data.kind', 'complex_resident')
        ->assertJsonPath('data.complex.id', $this->cx->id)
        ->assertJsonPath('data.complex.name', 'Kompleks B16');
    expect($members->isMember($this->cx->id, $user->id))->toBeTrue()
        ->and(DeviceUser::query()->where('user_id', $user->id)->count())->toBe(0)       // no automatic device access
        ->and(Subscription::query()->count())->toBe(0);                                 // no automatic subscription

    expect($as->getJson('/v1/me')->json('data.complexes'))->toBe([['id' => $this->cx->id, 'name' => 'Kompleks B16']]);
    $as->getJson("/v1/complexes/{$this->cx->id}/devices")->assertOk()
        ->assertJsonPath('data.0.id', $this->gate->id)
        ->assertJsonPath('data.0.my_subscription_status', 'none')
        ->assertJsonPath('data.0.subscription_price_minor', 1200);

    $orderId = $as->postJson("/v1/complexes/{$this->cx->id}/devices/{$this->gate->id}/subscribe", [], ['Idempotency-Key' => 'b16-sub-1'])
        ->assertCreated()->json('id');
    $as->getJson("/v1/complexes/{$this->cx->id}/devices")->assertJsonPath('data.0.my_subscription_status', 'pending_payment');
    $pending = $as->getJson('/v1/subscriptions?status=pending_payment')->assertOk()->json('data');
    expect($pending)->toHaveCount(1)
        ->and($pending[0])->toMatchArray(['tier' => 'main', 'status' => 'pending_payment', 'device_id' => $this->gate->id, 'price_minor' => 1200]);

    b16Pay(Order::query()->findOrFail($orderId));
    $as->getJson("/v1/complexes/{$this->cx->id}/devices")->assertJsonPath('data.0.my_subscription_status', 'active');
    expect($as->getJson('/v1/subscriptions?status=pending_payment')->json('data'))->toBe([]);

    // the same link can never be used twice
    $as->postJson("/v1/invites/{$token}/accept")->assertStatus(410)->assertJsonPath('errors.code', 'invitation_invalid');
    $this->getJson("/v1/invites/{$token}")->assertStatus(410)->assertJsonPath('errors.code', 'invitation_invalid');
});

it('a revoked invitation and the link of a resident the Komendant removed stay dead', function () {
    ['token' => $revoked, 'invitation' => $inv] = ($this->invite)('revoked@example.test');
    app(InvitationService::class)->revoke($inv);
    $this->getJson("/v1/invites/{$revoked}")->assertStatus(410);
    $this->actingAs(b16Verified('+994500160011', 'revoked@example.test'), 'user')
        ->postJson("/v1/invites/{$revoked}/accept")->assertStatus(410)->assertJsonPath('errors.code', 'invitation_invalid');

    ['token' => $token] = ($this->invite)('removed@example.test');
    $resident = b16Verified('+994500160012', 'removed@example.test');
    $this->actingAs($resident, 'user')->postJson("/v1/invites/{$token}/accept")->assertOk();
    $komendantUser = b16Verified('+994500160013', 'kom@example.test');
    $this->manager->forceFill(['user_id' => $komendantUser->id])->save();
    $this->actingAs($komendantUser, 'user')->deleteJson("/v1/komendant/residents/{$resident->id}")->assertOk();

    $as = $this->actingAs($resident, 'user');
    $as->postJson("/v1/invites/{$token}/accept")->assertStatus(410);                  // old link cannot re-admit
    expect($as->getJson('/v1/complexes')->json('data'))->toBe([]);
    $as->getJson("/v1/complexes/{$this->cx->id}/devices")->assertNotFound();
});

it('family invitation (sent by the resident): accept shape, own pending additional listed, member pays; outsider cannot', function () {
    ['token' => $token] = ($this->invite)('head@example.test');
    $head = b16Verified('+994500160021', 'head@example.test');
    $this->actingAs($head, 'user')->postJson("/v1/invites/{$token}/accept")->assertOk();
    $orderId = $this->actingAs($head, 'user')
        ->postJson("/v1/complexes/{$this->cx->id}/devices/{$this->gate->id}/subscribe", [], ['Idempotency-Key' => 'b16-head'])->json('id');
    b16Pay(Order::query()->findOrFail($orderId));

    $member = b16Verified('+994500160022', 'member@example.test');
    $this->actingAs($head, 'user')->postJson("/v1/devices/{$this->gate->id}/invitations", [
        'first_name' => 'Fam', 'last_name' => 'Üzv', 'email' => 'member@example.test',
    ])->assertCreated();
    $invitation = Invitation::query()->where('invitee_email', 'member@example.test')->latest('id')->firstOrFail();
    $familyToken = '';
    Bus::assertDispatched(SendInvitationEmailJob::class, function (SendInvitationEmailJob $job) use ($invitation, &$familyToken): bool {
        if ($job->invitationId === (int) $invitation->id) {
            $familyToken = $job->token;
        }

        return true;
    });

    $this->getJson("/v1/invites/{$familyToken}")->assertOk()->assertJsonPath('data.kind', 'family_member');
    $accepted = $this->actingAs($member, 'user')->postJson("/v1/invites/{$familyToken}/accept")->assertOk()->json('data');
    expect($accepted['kind'])->toBe('family_member')
        ->and($accepted['device']['id'])->toBe($this->gate->id)
        ->and($accepted)->toHaveKeys(['family_link_id', 'subscription_id']);

    $pending = $this->actingAs($member, 'user')->getJson('/v1/subscriptions?status=pending_payment')->json('data');
    expect($pending)->toHaveCount(1)
        ->and($pending[0])->toMatchArray(['id' => $accepted['subscription_id'], 'tier' => 'additional', 'device_id' => $this->gate->id]);

    $order = fn (User $payer, string $key) => $this->actingAs($payer, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_additional',
        'items' => [['item_type' => 'sub_additional', 'referenced_id' => $accepted['subscription_id'], 'quantity' => 1]],
    ], ['Idempotency-Key' => $key]);
    $order(b16Verified('+994500160023', 'outsider@example.test'), 'b16-x')->assertForbidden();
    $order($member, 'b16-self')->assertCreated()->assertJsonPath('amount_minor', 1200);

    // the member got no complex membership (B8: no escalation), and the same family link cannot be reused
    expect(app(ComplexMembershipService::class)->isMember($this->cx->id, $member->id))->toBeFalse();
    $this->actingAs($member, 'user')->postJson("/v1/invites/{$familyToken}/accept")->assertStatus(410);
});
