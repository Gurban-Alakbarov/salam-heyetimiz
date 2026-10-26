<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Auth\Models\UserDevice;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\Enums\VisitorAccessType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Y7 — the account owner deletes their own account from the app (DELETE /v1/me, store requirement), and every
| deletion (self or admin) erases the personal data at once. Y3 — the erased phone / email can register again
| (no more 500 from the unique indexes); a backfill command erases accounts deleted before this existed.
*/

beforeEach(function () {
    Bus::fake([SendInvitationEmailJob::class]);
    $this->cx = makeComplex('CX-Y7', 'Kompleks Y7');
    $this->gate = makeActiveDevice('SER-Y7-1', '+994700770701');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
});

function y7Register(string $email, string $phone): TestResponse
{
    return test()->postJson('/v1/auth/register', ['first_name' => 'Yeni', 'last_name' => 'Sakin', 'phone' => $phone, 'email' => $email]);
}

function y7Verify(string $email): TestResponse
{
    return test()->postJson('/v1/auth/verify-email', [
        'email' => $email,
        'code' => (string) app(FakeEmailOtpTransport::class)->lastCodeFor($email),
        'device' => ['install_uuid' => (string) Str::uuid(), 'platform' => 'android'],
    ]);
}

it('deletes my own account: access, family, memberships, sessions end; personal data is erased', function () {
    // a signed-in complex resident who heads a family, has a guest link, a pending invite and a Komendant link
    $tokens = authenticateViaOtp('+994505770701');
    $head = User::query()->where('phone', '+994505770701')->firstOrFail();
    $head->forceFill(['full_name' => 'Rəşad Məmmədov'])->save();
    UserDevice::query()->where('user_id', $head->id)->update(['push_token' => 'fcm-token-1', 'last_seen_ip' => '10.0.0.1']);
    $originalEmail = $head->email;

    app(ComplexMembershipService::class)->join($this->cx->id, $head);
    $headRow = makeDeviceUser($head, $this->gate, 'user');
    $headSub = makeSubscription($headRow, ['ends_at' => now()->addDays(20)]);
    [$link] = mintVisitorLink($this->gate, $head, VisitorAccessType::TimeLimited, 120, 'Kuryer');

    $member = makeUser('+994505770702');
    $family = FamilyLink::query()->create(['head_user_id' => $head->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    $memberRow = DeviceUser::query()->create(['device_id' => $this->gate->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active', 'family_link_id' => $family->id, 'added_by_user_id' => $head->id]);
    $memberSub = makeSubscription($memberRow, ['tier' => SubscriptionTier::Additional, 'ends_at' => now()->addDays(10)]);

    $invite = app(InvitationService::class)->createFamilyMember($head, 'qohum@test.az', 'Qohum', 'Bir', $this->gate->id)['invitation'];
    $komendant = makeAdminRole('complex_manager', $this->cx->id);
    $komendant->forceFill(['user_id' => $head->id])->save();

    $this->withHeaders(bearer($tokens['access_token']))->deleteJson('/v1/me', ['confirm' => true])->assertNoContent();

    $ghost = User::withTrashed()->findOrFail($head->id);
    expect($ghost->trashed())->toBeTrue()
        ->and($ghost->status->value)->toBe('self_deleted')
        ->and($ghost->phone)->toBe('del:'.$head->id)
        ->and($ghost->email)->toBeNull()
        ->and($ghost->full_name)->toBeNull()
        ->and($ghost->email_verified_at)->toBeNull()
        ->and($ghost->last_login_ip)->toBeNull();

    // access + subscriptions (no refund), the family it headed, its memberships, invite, link, sessions, devices
    expect($headRow->fresh()->status)->toBe(DeviceUserStatus::Revoked)
        ->and($headSub->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($headSub->fresh()->cancellation_reason)->toBe('resident_deleted')
        ->and($memberRow->fresh()->status)->toBe(DeviceUserStatus::Revoked)
        ->and($memberSub->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($memberSub->fresh()->cancellation_reason)->toBe('removed_by_family_head')
        ->and($family->fresh()->status->value)->toBe('removed')
        ->and(ComplexMember::query()->where('user_id', $head->id)->where('status', 'active')->exists())->toBeFalse()
        ->and(Invitation::query()->find($invite->id)->status->value)->toBe('cancelled')
        ->and($link->fresh()->revoked_at)->not->toBeNull()
        ->and($komendant->fresh()->user_id)->toBeNull()
        ->and(RefreshToken::query()->where('user_id', $head->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(UserDevice::query()->where('user_id', $head->id)->whereNotNull('push_token')->exists())->toBeFalse()
        ->and(UserDevice::query()->where('user_id', $head->id)->whereNull('revoked_at')->exists())->toBeFalse();

    $audit = AuditLog::query()->where('action', 'resident.deleted')->latest('id')->firstOrFail();
    expect($audit->payload['self_service'])->toBeTrue()
        ->and($audit->payload['phone_sha256'])->toBe(hash('sha256', '+994505770701'))
        ->and($audit->payload['email_sha256'])->toBe(hash('sha256', $originalEmail))
        ->and(json_encode($audit->payload))->not->toContain('+994505770701');

    // the access token of the deleted account no longer works (a new request = a fresh guard, as in production)
    app('auth')->forgetGuards();
    $this->withHeaders(bearer($tokens['access_token']))->getJson('/v1/me')->assertUnauthorized();
    $this->postJson('/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertUnauthorized();
});

it('requires an explicit confirmation and changes nothing without it', function () {
    $tokens = authenticateViaOtp('+994505770703');

    $this->withHeaders(bearer($tokens['access_token']))->deleteJson('/v1/me')->assertStatus(422);
    $this->withHeaders(bearer($tokens['access_token']))->deleteJson('/v1/me', ['confirm' => false])->assertStatus(422);

    expect(User::query()->where('phone', '+994505770703')->exists())->toBeTrue();
});

it('a device owner is refused (successor_required) and nothing changes', function () {
    $owner = makeUser('+994505770704');
    $device = makeOwnedDevice($owner, 'SER-Y7-2', '+994700770702');

    $this->withHeaders(bearer(userAccessToken($owner)))->deleteJson('/v1/me', ['confirm' => true])
        ->assertStatus(409)->assertJsonPath('error.code', 'successor_required');

    expect($owner->fresh()->trashed())->toBeFalse()
        ->and($owner->fresh()->phone)->toBe('+994505770704')
        ->and((int) Device::query()->find($device->id)->owner_user_id)->toBe($owner->id);
});

it('Y3: the same phone and email register again right after a self-deletion', function () {
    $tokens = authenticateViaOtp('+994505770705');
    $old = User::query()->where('phone', '+994505770705')->firstOrFail();
    $email = $old->email;

    $this->withHeaders(bearer($tokens['access_token']))->deleteJson('/v1/me', ['confirm' => true])->assertNoContent();

    y7Register($email, '+994505770705')->assertStatus(202);
    $fresh = y7Verify($email)->assertOk()->json('data.user.id');
    expect($fresh)->not->toBe($old->id);
});

it('Y3: an admin deletion also erases the data, so the phone and email register again', function () {
    $user = makeUser('+994505770706');
    $user->forceFill(['email' => 'admin-del@test.az', 'email_verified_at' => now(), 'full_name' => 'Silinən Sakin'])->save();

    $this->actingAs(makeSuperAdmin(), 'admin')->deleteJson("/admin/v1/residents/{$user->id}")->assertNoContent();

    $ghost = User::withTrashed()->findOrFail($user->id);
    expect($ghost->phone)->toBe('del:'.$user->id)->and($ghost->email)->toBeNull()->and($ghost->full_name)->toBeNull()
        ->and($ghost->status->value)->toBe('active'); // an admin removal is not a self-deletion

    y7Register('admin-del@test.az', '+994505770706')->assertStatus(202);
    y7Verify('admin-del@test.az')->assertOk();
});

it('the backfill erases accounts deleted before anonymisation existed (idempotent; dry-run changes nothing)', function () {
    $old = makeUser('+994505770707');
    $old->forceFill(['email' => 'kohne@test.az', 'full_name' => 'Köhnə Hesab'])->save();
    $old->delete(); // the pre-Y7 deletion: soft-deleted, data kept

    // that is exactly the Y3 failure: the unique indexes still hold the phone and email
    expect(fn () => User::query()->create(['phone' => '+994505770707']))->toThrow(QueryException::class);

    $this->artisan('users:anonymise-deleted', ['--dry-run' => true])->assertSuccessful();
    expect(User::withTrashed()->find($old->id)->phone)->toBe('+994505770707');

    $this->artisan('users:anonymise-deleted')->expectsOutputToContain('anonymised 1')->assertSuccessful();
    $ghost = User::withTrashed()->find($old->id);
    expect($ghost->phone)->toBe('del:'.$old->id)->and($ghost->email)->toBeNull()->and($ghost->full_name)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.anonymised_backfill')->count())->toBe(1);

    $this->artisan('users:anonymise-deleted')->expectsOutputToContain('anonymised 0')->assertSuccessful();

    y7Register('kohne@test.az', '+994505770707')->assertStatus(202);
});
