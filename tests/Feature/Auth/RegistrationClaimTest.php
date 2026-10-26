<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Devices\Models\Device;
use App\Domain\Users\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| K1 — a phone number alone never hands over an account with LIVE access. An unverified account that owns a
| device / has an active roster row / complex membership / family link / Komendant link (typically created by
| phone when an admin assigns a device) is continued by registration ONLY with the email recorded on it by an
| admin — the email OTP then proves the registrant owns it. A refused attempt changes nothing on the row.
| Rows with history only (revoked access) keep the old behaviour, so nobody legitimate is locked out.
*/

beforeEach(function () {
    $this->super = makeSuperAdmin();
});

function k1Register(string $email, string $phone, string $first = 'Aysel', string $last = 'Məmmədova'): TestResponse
{
    return test()->postJson('/v1/auth/register', ['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'email' => $email]);
}

function k1Verify(string $email): TestResponse
{
    return test()->postJson('/v1/auth/verify-email', [
        'email' => $email,
        'code' => (string) app(FakeEmailOtpTransport::class)->lastCodeFor($email),
        'device' => ['install_uuid' => (string) Str::uuid(), 'platform' => 'android'],
    ]);
}

/** @return array{0: Device, 1: User} an owner provisioned by phone through the real admin assign endpoint */
function k1AssignedOwner(AdminUser $admin, string $serial, string $sim, string $phone, ?string $email = null): array
{
    $device = makeUnassignedDevice($serial, $sim);
    test()->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/assign", array_filter([
        'owner_phone' => $phone, 'owner_email' => $email,
    ]))->assertOk();

    return [$device->refresh(), User::query()->where('phone', $phone)->firstOrFail()];
}

it('refuses to hand a phone-only owner to another email and leaves the account untouched', function () {
    [$device, $owner] = k1AssignedOwner($this->super, 'SER-K1-1', '+994700610001', '+994505610001');
    $owner->forceFill(['full_name' => 'Real Sahib'])->save();

    k1Register('attacker@test.az', '+994505610001')
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.code', 'phone_linked_to_account');

    $owner->refresh();
    expect($owner->email)->toBeNull()
        ->and($owner->full_name)->toBe('Real Sahib')
        ->and($owner->email_verified_at)->toBeNull()
        ->and(app(FakeEmailOtpTransport::class)->lastCodeFor('attacker@test.az'))->toBeNull()
        ->and(User::query()->where('email', 'attacker@test.az')->exists())->toBeFalse()
        ->and((int) $device->refresh()->owner_user_id)->toBe($owner->id);

    $blocked = AuditLog::query()->where('action', 'registration.claim_blocked')->firstOrFail();
    expect($blocked->payload['user_id'])->toBe($owner->id)
        ->and($blocked->payload['email'])->not->toContain('attacker');
});

it('the owner registers with the email an admin recorded and gets the SAME account and device', function () {
    [$device, $owner] = k1AssignedOwner($this->super, 'SER-K1-2', '+994700610002', '+994505610002');

    $this->actingAs($this->super, 'admin')->putJson("/admin/v1/residents/{$owner->id}/email", ['email' => 'Real.Owner@Test.az'])
        ->assertOk()
        ->assertJsonPath('email', 'real.owner@test.az')
        ->assertJsonPath('email_verified', false);
    $recorded = AuditLog::query()->where('action', 'resident.email_recorded')->firstOrFail();
    expect($recorded->payload['to'])->toBe('re********@test.az');

    k1Register('real.owner@test.az', '+994505610002')->assertStatus(202);
    k1Verify('real.owner@test.az')->assertOk()->assertJsonPath('data.user.id', $owner->id);

    expect($owner->refresh()->email_verified_at)->not->toBeNull()
        ->and((int) $device->refresh()->owner_user_id)->toBe($owner->id);

    // once verified, the phone is simply taken (pre-existing rule)
    k1Register('someone@test.az', '+994505610002')->assertStatus(422);
});

it('an email given at assignment (owner) or roster add (member) lets them register straight away', function () {
    [$device, $owner] = k1AssignedOwner($this->super, 'SER-K1-3', '+994700610003', '+994505610003', 'Owner3@Test.az');
    expect($owner->email)->toBe('owner3@test.az')->and($owner->email_verified_at)->toBeNull();

    k1Register('owner3@test.az', '+994505610003')->assertStatus(202);
    k1Verify('owner3@test.az')->assertOk()->assertJsonPath('data.user.id', $owner->id);

    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", [
        'phone' => '+994505610013', 'email' => 'member3@test.az',
    ])->assertCreated();
    $member = User::query()->where('phone', '+994505610013')->firstOrFail();
    expect($member->email)->toBe('member3@test.az');

    k1Register('other@test.az', '+994505610013')->assertStatus(409)->assertJsonPath('errors.code', 'phone_linked_to_account');
    k1Register('member3@test.az', '+994505610013')->assertStatus(202);
    k1Verify('member3@test.az')->assertOk()->assertJsonPath('data.user.id', $member->id);
});

it('transfer records the new owner email the same way', function () {
    [$device] = k1AssignedOwner($this->super, 'SER-K1-4', '+994700610004', '+994505610004');

    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$device->id}/transfer", [
        'new_owner_phone' => '+994505610014', 'new_owner_email' => 'new4@test.az', 'reason' => 'satış',
    ])->assertOk();

    expect(User::query()->where('phone', '+994505610014')->value('email'))->toBe('new4@test.az');
    k1Register('new4@test.az', '+994505610014')->assertStatus(202);
});

it('a live account continued with its recorded email but another phone is refused; the phone stays', function () {
    [, $owner] = k1AssignedOwner($this->super, 'SER-K1-5', '+994700610005', '+994505610005', 'owner5@test.az');

    k1Register('owner5@test.az', '+994509999905')->assertStatus(409)->assertJsonPath('errors.code', 'registration_conflict');

    expect($owner->refresh()->phone)->toBe('+994505610005');
});

it('a live account keeps its recorded name until the email is proven; a blank name is filled', function () {
    [, $named] = k1AssignedOwner($this->super, 'SER-K1-6', '+994700610006', '+994505610006', 'owner6@test.az');
    $named->forceFill(['full_name' => 'Real Sahib'])->save();
    k1Register('owner6@test.az', '+994505610006', 'Başqa', 'Ad')->assertStatus(202);
    expect($named->refresh()->full_name)->toBe('Real Sahib');

    [, $blank] = k1AssignedOwner($this->super, 'SER-K1-7', '+994700610007', '+994505610007', 'owner7@test.az');
    k1Register('owner7@test.az', '+994505610007')->assertStatus(202);
    expect($blank->refresh()->full_name)->toBe('Aysel Məmmədova');
});

it('a phone row with history only (revoked access) is still continued by a new registration, as before', function () {
    $legacy = makeUser('+994505610008');
    makeDeviceUser($legacy, makeActiveDevice('SER-K1-8', '+994700610008'), 'user', 'revoked');

    k1Register('legacy8@test.az', '+994505610008')->assertStatus(202);
    k1Verify('legacy8@test.az')->assertOk()->assertJsonPath('data.user.id', $legacy->id);
});

it('recording an email: verified accounts keep theirs, emails of others are refused, input is validated', function () {
    $verified = makeUser('+994505610009');
    $verified->forceFill(['email' => 'mine@test.az', 'email_verified_at' => now()])->save();
    $as = $this->actingAs($this->super, 'admin');

    $as->putJson("/admin/v1/residents/{$verified->id}/email", ['email' => 'other@test.az'])
        ->assertStatus(409)->assertJsonPath('error.code', 'email_already_verified');
    $as->putJson("/admin/v1/residents/{$verified->id}/email", ['email' => 'MINE@test.az'])->assertOk()->assertJsonPath('email_verified', true);

    $target = makeUser('+994505610010');
    $as->putJson("/admin/v1/residents/{$target->id}/email", ['email' => 'mine@test.az'])
        ->assertStatus(409)->assertJsonPath('error.code', 'email_taken');

    $gone = makeUser('+994505610011');
    $gone->forceFill(['email' => 'gone@test.az'])->save();
    $gone->delete();
    $as->putJson("/admin/v1/residents/{$target->id}/email", ['email' => 'gone@test.az'])
        ->assertStatus(409)->assertJsonPath('error.code', 'email_taken');

    $as->putJson("/admin/v1/residents/{$target->id}/email", ['email' => 'not-an-email'])->assertStatus(422);
    $as->putJson('/admin/v1/residents/999999/email', ['email' => 'x@test.az'])->assertNotFound();
    expect($target->refresh()->email)->toBeNull();
});

it('an assignment with an email that belongs to someone else is refused as a whole', function () {
    makeUser('+994505610012')->forceFill(['email' => 'taken@test.az'])->save();
    $device = makeUnassignedDevice('SER-K1-12', '+994700610012');

    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$device->id}/assign", [
        'owner_phone' => '+994505610022', 'owner_email' => 'taken@test.az',
    ])->assertStatus(409)->assertJsonPath('error.code', 'email_taken');

    expect($device->refresh()->owner_user_id)->toBeNull()
        ->and(User::query()->where('phone', '+994505610022')->exists())->toBeFalse();
});

it('setting an email needs residents.update and, for complex_manager, a resident of its own complex', function () {
    $cx = makeComplex('CX-K1', 'Kompleks K1');
    $inside = makeActiveDevice('SER-K1-13', '+994700610013');
    $inside->forceFill(['complex_id' => $cx->id])->save();
    $outside = makeActiveDevice('SER-K1-14', '+994700610014');

    $mine = makeUser('+994505610013');
    makeDeviceUser($mine, $inside, 'user');
    $theirs = makeUser('+994505610014');
    makeDeviceUser($theirs, $outside, 'user');

    $this->actingAs(makeAdminRole('support'), 'admin')
        ->putJson("/admin/v1/residents/{$mine->id}/email", ['email' => 'a@test.az'])->assertForbidden();

    $manager = makeAdminRole('complex_manager', $cx->id);
    $this->actingAs($manager, 'admin')->putJson("/admin/v1/residents/{$theirs->id}/email", ['email' => 'b@test.az'])->assertForbidden();
    $this->actingAs($manager, 'admin')->putJson("/admin/v1/residents/{$mine->id}/email", ['email' => 'c@test.az'])->assertOk();
});

it('the residents directory tells admins whether the email is proven', function () {
    [, $owner] = k1AssignedOwner($this->super, 'SER-K1-15', '+994700610015', '+994505610015', 'owner15@test.az');

    $row = collect($this->actingAs($this->super, 'admin')->getJson('/admin/v1/residents')->assertOk()->json('data'))
        ->firstWhere('user_id', $owner->id);
    expect($row['email'])->toBe('owner15@test.az')->and($row['email_verified'])->toBeFalse();
});
