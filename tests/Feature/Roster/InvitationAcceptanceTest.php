<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Roster\Actions\ClaimInvitation;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/*
| IMPLEMENTATION_PLAN B6 — invitation link → account → active complex membership. New users claim through
| register → verify-email; existing users accept. Only the invitee's verified email can claim; one claim wins.
*/

function b6Otp(string $email): string
{
    return (string) app(FakeEmailOtpTransport::class)->lastCodeFor(mb_strtolower($email));
}

function b6Device(): array
{
    return ['install_uuid' => (string) Str::uuid(), 'platform' => 'android', 'app_version' => '1.0.0'];
}

function b6Verified(string $phone, string $email): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => $email, 'email_verified_at' => now()])->save();

    return $u->refresh();
}

beforeEach(function () {
    Bus::fake([SendInvitationEmailJob::class]);
    $this->cx = makeComplex('CX-B6', 'Kompleks B6');
    $this->manager = makeAdminRole('complex_manager', $this->cx->id);
    $this->invite = fn (string $email = 'nigar@example.test') => app(InvitationService::class)
        ->createComplexResident($this->cx->id, $this->manager, $email, 'Nigar', 'Əliyeva');
    $this->members = app(ComplexMembershipService::class);
});

it('looks up a live invitation (masked email, no ids) and answers ONE 410 for every dead token', function () {
    ['token' => $token, 'invitation' => $inv] = ($this->invite)();

    $data = $this->getJson("/v1/invites/{$token}")->assertOk()->json('data');
    expect($data)->toMatchArray(['kind' => 'complex_resident', 'complex_name' => 'Kompleks B6', 'first_name' => 'Nigar', 'email_masked' => 'n****@example.test'])
        ->and($data)->not->toHaveKey('id')->not->toHaveKey('complex_id');

    $unknown = $this->getJson('/v1/invites/not-a-real-token')->assertStatus(410)->json();
    ['token' => $t2] = ($this->invite)('second@example.test');
    Invitation::query()->where('invitee_email', 'second@example.test')->update(['status' => InvitationStatus::Cancelled->value]);
    ['token' => $t3] = ($this->invite)('third@example.test');
    Invitation::query()->where('invitee_email', 'third@example.test')->update(['expires_at' => now()->subMinute()]);

    expect($this->getJson("/v1/invites/{$t2}")->assertStatus(410)->json())->toBe($unknown)
        ->and($this->getJson("/v1/invites/{$t3}")->assertStatus(410)->json())->toBe($unknown);
});

it('new user: register with the token → verify-email claims it → active complex membership', function () {
    ['token' => $token, 'invitation' => $inv] = ($this->invite)();
    $body = ['first_name' => 'Nigar', 'last_name' => 'Əliyeva', 'phone' => '+994500060001', 'email' => 'Nigar@Example.test', 'invitation_token' => $token];

    // a different email cannot register against this invitation
    $this->postJson('/v1/auth/register', ['email' => 'other@example.test'] + $body)
        ->assertStatus(403)->assertJsonPath('errors.code', 'invitation_email_mismatch');

    $this->postJson('/v1/auth/register', $body)->assertStatus(202);
    $res = $this->postJson('/v1/auth/verify-email', [
        'email' => 'nigar@example.test', 'code' => b6Otp('nigar@example.test'), 'device' => b6Device(), 'invitation_token' => $token,
    ])->assertOk();

    $res->assertJsonPath('data.invitation.claimed', true)->assertJsonPath('data.invitation.complex.id', $this->cx->id);
    $user = User::query()->where('email', 'nigar@example.test')->firstOrFail();
    expect($this->members->isMember($this->cx->id, $user->id))->toBeTrue()
        ->and(ComplexMember::query()->where('user_id', $user->id)->value('invitation_id'))->toBe($inv->id);
    $inv->refresh();
    expect($inv->status)->toBe(InvitationStatus::Accepted)->and((int) $inv->invitee_user_id)->toBe($user->id)->and($inv->accepted_at)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'invitation.accepted')->count())->toBe(1);
});

it('existing user accepts with the matching verified email; mismatch / unverified are refused', function () {
    ['token' => $token, 'invitation' => $inv] = ($this->invite)();

    $this->actingAs(b6Verified('+994500060011', 'someone@example.test'), 'user')
        ->postJson("/v1/invites/{$token}/accept")->assertStatus(403)->assertJsonPath('errors.code', 'invitation_email_mismatch');

    $unverified = makeUser('+994500060012');
    $unverified->forceFill(['email' => 'nigar@example.test'])->save();
    $this->actingAs($unverified->refresh(), 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(403);
    expect($inv->refresh()->status)->toBe(InvitationStatus::Pending);

    $unverified->forceFill(['email' => 'NIGAR@example.test', 'email_verified_at' => now()])->save();
    $owner = $unverified->refresh();
    $this->actingAs($owner, 'user')->postJson("/v1/invites/{$token}/accept")
        ->assertOk()->assertJsonPath('data.complex.name', 'Kompleks B6');
    expect($this->members->isMember($this->cx->id, $owner->id))->toBeTrue();

    // used → the same uniform 410
    $this->actingAs($owner, 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(410)->assertJsonPath('errors.code', 'invitation_invalid');
    $this->getJson("/v1/invites/{$token}")->assertStatus(410);
});

it('refuses an expired invitation everywhere (register, accept) without creating membership', function () {
    ['token' => $token] = ($this->invite)();
    $this->travel(7)->days();
    $this->travel(1)->minutes();

    $this->postJson('/v1/auth/register', ['first_name' => 'N', 'last_name' => 'Ə', 'phone' => '+994500060021', 'email' => 'nigar@example.test', 'invitation_token' => $token])
        ->assertStatus(410);
    $user = b6Verified('+994500060022', 'nigar@example.test');
    $this->actingAs($user, 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(410);
    expect(ComplexMember::query()->count())->toBe(0);
});

it('a token that dies between register and verify does not block login (claim reported, not fatal)', function () {
    ['token' => $token, 'invitation' => $inv] = ($this->invite)();
    $this->postJson('/v1/auth/register', ['first_name' => 'N', 'last_name' => 'Ə', 'phone' => '+994500060031', 'email' => 'nigar@example.test', 'invitation_token' => $token])->assertStatus(202);
    app(InvitationService::class)->revoke($inv);

    $this->postJson('/v1/auth/verify-email', ['email' => 'nigar@example.test', 'code' => b6Otp('nigar@example.test'), 'device' => b6Device(), 'invitation_token' => $token])
        ->assertOk()->assertJsonPath('data.invitation.claimed', false)->assertJsonPath('data.invitation.code', 'invitation_invalid');
    expect(ComplexMember::query()->count())->toBe(0);
});

it('double claim: only one wins even from a stale, still-pending model (conditional update)', function () {
    ['invitation' => $inv] = ($this->invite)();
    $user = b6Verified('+994500060041', 'nigar@example.test');
    $staleA = Invitation::query()->findOrFail($inv->id);
    $staleB = Invitation::query()->findOrFail($inv->id);
    $claim = app(ClaimInvitation::class);

    $claim->handle($staleA, $user);
    expect(fn () => $claim->handle($staleB, $user))->toThrow(InvitationException::class);
    expect(ComplexMember::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'invitation.accepted')->count())->toBe(1);
});

it('decline by the invitee closes the invitation', function () {
    ['token' => $token, 'invitation' => $inv] = ($this->invite)();
    $user = b6Verified('+994500060051', 'nigar@example.test');

    $this->actingAs($user, 'user')->postJson("/v1/invites/{$token}/decline")->assertOk();
    expect($inv->refresh()->status)->toBe(InvitationStatus::Declined)
        ->and(ComplexMember::query()->count())->toBe(0);
    $this->getJson("/v1/invites/{$token}")->assertStatus(410);
});

it('family invitations stay pending until family links exist (B8)', function () {
    $head = b6Verified('+994500060061', 'head@example.test');
    ['token' => $token, 'invitation' => $inv] = app(InvitationService::class)->createFamilyMember($head, 'kid@example.test', 'Kid', 'Head');
    $kid = b6Verified('+994500060062', 'kid@example.test');

    $this->actingAs($kid, 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(409)->assertJsonPath('errors.code', 'invitation_kind_unsupported');
    expect($inv->refresh()->status)->toBe(InvitationStatus::Pending);
});

it('serves the web landing and the app-link association files (404 until configured)', function () {
    ['token' => $token] = ($this->invite)();
    $this->get("/invite/{$token}")->assertOk()->assertSee('Kompleks B6')->assertSee('Tətbiqdə aç');
    $this->get('/invite/dead-token')->assertStatus(410)->assertSee('Dəvət etibarsızdır');

    $this->get('/.well-known/assetlinks.json')->assertNotFound();
    $this->get('/.well-known/apple-app-site-association')->assertNotFound();

    config(['domain.app_links.android.sha256_cert_fingerprints' => ['AA:BB'], 'domain.app_links.ios.app_id' => 'TEAM123.com.salamheyetimiz.salamMobile']);
    $this->get('/.well-known/assetlinks.json')->assertOk()
        ->assertJsonPath('0.target.package_name', 'com.salamheyetimiz.salam_mobile')
        ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB']);
    $this->get('/.well-known/apple-app-site-association')->assertOk()
        ->assertJsonPath('applinks.details.0.appIDs', ['TEAM123.com.salamheyetimiz.salamMobile'])
        ->assertJsonPath('applinks.details.0.components', [['/' => '/invite/*']]);
});
