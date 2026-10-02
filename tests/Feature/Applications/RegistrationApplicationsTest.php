<?php

use App\Domain\Admin\Models\Complex;
use App\Domain\Applications\Jobs\SendApplicationRejectedEmailJob;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/*
| IMPLEMENTATION_PLAN B9 — physical / legal registration applications: separate models + endpoints, OSM
| latitude/longitude, admin lifecycle (approval creates the complex in one transaction), legacy users intact.
*/

function b9User(string $phone, ?string $type = null): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => 'u'.substr($phone, -4).'@example.test', 'email_verified_at' => now(), 'account_type' => $type])->save();

    return $u->refresh();
}

function b9Individual(array $over = []): array
{
    return $over + ['full_name' => 'Rauf Həsənov', 'phone' => '+994501110001', 'email' => 'rauf@example.test',
        'address' => 'Bakı, Nəsimi r., Həyət 12', 'latitude' => 40.4093, 'longitude' => 49.8671, 'note' => 'Darvaza 4m'];
}

function b9Legal(array $over = []): array
{
    return $over + ['complex_name' => 'Gənclik Park', 'legal_name' => 'Gənclik Park MMC', 'voen' => '1234567891',
        'legal_address' => 'Bakı, Atatürk pr. 1', 'contact_person_name' => 'Leyla Quliyeva', 'contact_phone' => '+994551112233',
        'contact_email' => 'leyla@example.test', 'address' => 'Bakı, Atatürk pr. 1A', 'latitude' => 40.4012345, 'longitude' => 49.8512345,
        'apartments_count' => 120];
}

beforeEach(function () {
    Bus::fake([SendApplicationRejectedEmailJob::class]);
});

it('registers with an optional account type; omitting it keeps the legacy NULL', function () {
    $this->postJson('/v1/auth/register', ['first_name' => 'A', 'last_name' => 'B', 'phone' => '+994500090001', 'email' => 'legal@example.test', 'account_type' => 'legal'])->assertStatus(202);
    $this->postJson('/v1/auth/register', ['first_name' => 'C', 'last_name' => 'D', 'phone' => '+994500090002', 'email' => 'plain@example.test'])->assertStatus(202);
    $this->postJson('/v1/auth/register', ['first_name' => 'E', 'last_name' => 'F', 'phone' => '+994500090003', 'email' => 'bad@example.test', 'account_type' => 'company'])->assertStatus(422);

    expect(User::query()->where('email', 'legal@example.test')->first()->getAttributes()['account_type'])->toBe('legal')
        ->and(User::query()->where('email', 'plain@example.test')->first()->getAttributes()['account_type'])->toBeNull();

    $code = (string) app(FakeEmailOtpTransport::class)->lastCodeFor('legal@example.test');
    $this->postJson('/v1/auth/verify-email', ['email' => 'legal@example.test', 'code' => $code, 'device' => ['install_uuid' => (string) Str::uuid(), 'platform' => 'android']])
        ->assertOk()->assertJsonPath('data.user.account_type', 'legal');
});

it('physical application: stored with OSM lat/lng, account typed, one open at a time, nothing else created', function () {
    $user = b9User('+994500090011');
    $res = $this->actingAs($user, 'user')->postJson('/v1/applications/individual', b9Individual())->assertCreated();
    $res->assertJsonPath('data.status', 'new')->assertJsonPath('data.location.latitude', 40.4093)->assertJsonPath('data.editable', true);

    expect($user->refresh()->getAttributes()['account_type'])->toBe('physical')
        ->and(Complex::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'application.individual_submitted')->count())->toBe(1);

    $this->actingAs($user, 'user')->postJson('/v1/applications/individual', b9Individual())->assertStatus(409)->assertJsonPath('error.code', 'application_already_open');
    // a physical account cannot file a legal application (separate models)
    $this->actingAs($user, 'user')->postJson('/v1/applications/legal', b9Legal())->assertStatus(409)->assertJsonPath('error.code', 'account_type_mismatch');
});

it('validates location (service area), VÖEN and phone server-side', function () {
    $user = b9User('+994500090021');
    $as = $this->actingAs($user, 'user');
    $as->postJson('/v1/applications/individual', b9Individual(['latitude' => 0, 'longitude' => 0]))->assertStatus(422);
    $as->postJson('/v1/applications/individual', b9Individual(['latitude' => 'abc']))->assertStatus(422);
    $as->postJson('/v1/applications/individual', b9Individual(['phone' => '0501112233']))->assertStatus(422);
    $as->postJson('/v1/applications/legal', b9Legal(['voen' => '12345']))->assertStatus(422);
    $as->postJson('/v1/applications/legal', b9Legal(['longitude' => 60.1]))->assertStatus(422);
    expect(IndividualApplication::query()->count() + LegalEntityApplication::query()->count())->toBe(0)
        ->and($user->refresh()->getAttributes()['account_type'])->toBeNull();
});

it('legal application: pending; one pending per VÖEN; applicant sees / edits only their own while pending', function () {
    $owner = b9User('+994500090031');
    $id = $this->actingAs($owner, 'user')->postJson('/v1/applications/legal', b9Legal())->assertCreated()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.voen', '1234567891')->json('data.id');
    expect($owner->refresh()->getAttributes()['account_type'])->toBe('legal')->and(Complex::query()->count())->toBe(0);

    $other = b9User('+994500090032');
    $this->actingAs($other, 'user')->postJson('/v1/applications/legal', b9Legal())->assertStatus(409)->assertJsonPath('error.code', 'application_duplicate_voen');
    $this->actingAs($other, 'user')->putJson("/v1/applications/legal/{$id}", b9Legal(['complex_name' => 'Hack']))->assertNotFound();
    $this->actingAs($other, 'user')->getJson('/v1/applications/mine')->assertOk()->assertJsonPath('data.legal', []);

    $this->actingAs($owner, 'user')->putJson("/v1/applications/legal/{$id}", b9Legal(['complex_name' => 'Gənclik Park 2']))->assertOk()->assertJsonPath('data.complex_name', 'Gənclik Park 2');
    $this->actingAs($owner, 'user')->getJson('/v1/applications/mine')->assertOk()
        ->assertJsonPath('data.account_type', 'legal')->assertJsonCount(1, 'data.legal')->assertJsonPath('data.individual', []);
});

it('admin approves a legal application: complex created in one transaction with lat/lng; no Komendant / membership', function () {
    $owner = b9User('+994500090041');
    $id = $this->actingAs($owner, 'user')->postJson('/v1/applications/legal', b9Legal())->json('data.id');
    $super = makeSuperAdmin();

    $res = $this->actingAs($super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');
    $complex = Complex::query()->findOrFail($res->json('data.complex.id'));
    expect($complex->name)->toBe('Gənclik Park')
        ->and((float) $complex->latitude)->toBe(40.4012345)->and((float) $complex->longitude)->toBe(49.8512345)
        ->and((int) $complex->legal_entity_application_id)->toBe($id)
        ->and($complex->code)->toBe('LE-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT))
        ->and((int) LegalEntityApplication::query()->find($id)->complex_id)->toBe($complex->id)
        ->and(ComplexMember::query()->count())->toBe(0)
        ->and(\App\Domain\Admin\Models\AdminUser::query()->where('role', 'complex_manager')->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'application.legal_approved')->count())->toBe(1);

    // terminal: second approve / reject refused; applicant can no longer edit
    $this->actingAs($super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/approve")->assertStatus(409);
    $this->actingAs($super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/reject", ['reason' => 'late'])->assertStatus(409);
    expect(Complex::query()->count())->toBe(1);
    $this->actingAs($owner, 'user')->putJson("/v1/applications/legal/{$id}", b9Legal())->assertStatus(409)->assertJsonPath('error.code', 'application_not_editable');
});

it('admin rejects a legal application with a reason and emails the applicant; the VÖEN may apply again', function () {
    $owner = b9User('+994500090051');
    $id = $this->actingAs($owner, 'user')->postJson('/v1/applications/legal', b9Legal())->json('data.id');
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/reject", [])->assertStatus(422);
    $this->actingAs($super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/reject", ['reason' => 'VÖEN uyğun gəlmir'])
        ->assertOk()->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'VÖEN uyğun gəlmir');
    Bus::assertDispatched(SendApplicationRejectedEmailJob::class, fn ($j) => $j->applicationId === $id);
    expect(SendApplicationRejectedEmailJob::emailData(LegalEntityApplication::query()->find($id))['lines'][2])->toContain('VÖEN uyğun gəlmir')
        ->and(Complex::query()->count())->toBe(0);

    $this->actingAs($owner, 'user')->postJson('/v1/applications/legal', b9Legal())->assertCreated(); // no longer pending → allowed
});

it('admin moves a physical application through its pipeline; invalid transitions refused; audited', function () {
    $user = b9User('+994500090061');
    $id = $this->actingAs($user, 'user')->postJson('/v1/applications/individual', b9Individual())->json('data.id');
    $super = makeSuperAdmin();
    $patch = fn (array $b) => $this->actingAs($super, 'admin')->patchJson("/admin/v1/applications/individual/{$id}", $b);

    $patch(['status' => 'installed'])->assertStatus(422)->assertJsonPath('error.code', 'application_invalid_transition');
    $patch(['status' => 'contacted', 'admin_note' => 'Zəng edildi'])->assertOk()->assertJsonPath('data.status', 'contacted');
    $this->actingAs($user, 'user')->putJson("/v1/applications/individual/{$id}", b9Individual())->assertStatus(409); // no longer editable
    $patch(['status' => 'in_progress'])->assertOk();

    $complexDevice = makeActiveDevice('SER-B9-C', '+994700090001');
    $complexDevice->forceFill(['ownership_mode' => 'complex'])->save();
    $patch(['status' => 'installed', 'device_id' => $complexDevice->id])->assertStatus(422)->assertJsonPath('error.code', 'application_invalid_device');

    $private = makeOwnedDevice($user, 'SER-B9-P', '+994700090002');
    $patch(['status' => 'installed', 'device_id' => $private->id])->assertOk()->assertJsonPath('data.status', 'installed')->assertJsonPath('data.device_id', $private->id);
    $patch(['status' => 'rejected', 'rejection_reason' => 'x y z'])->assertStatus(422); // terminal

    expect(AuditLog::query()->where('action', 'application.individual_status_changed')->count())->toBe(3);
    // closed → the user may open a new one
    $this->actingAs($user, 'user')->postJson('/v1/applications/individual', b9Individual())->assertCreated();
});

it('admin permissions: view vs manage; approval also needs complexes.manage; others denied', function () {
    $this->getJson('/v1/applications/mine')->assertStatus(401);
    $user = b9User('+994500090071');
    $legalId = $this->actingAs($user, 'user')->postJson('/v1/applications/legal', b9Legal())->json('data.id');

    $this->actingAs(makeAdminRole('finance'), 'admin')->getJson('/admin/v1/applications/legal')->assertForbidden();
    $this->actingAs(makeAdminRole('complex_manager'), 'admin')->getJson('/admin/v1/applications/legal')->assertForbidden();
    $this->actingAs(makeAdminRole('support'), 'admin')->getJson('/admin/v1/applications/legal')->assertOk()->assertJsonPath('meta.total', 1);
    $this->actingAs(makeAdminRole('support'), 'admin')->postJson("/admin/v1/applications/legal/{$legalId}/reject", ['reason' => 'nope'])->assertForbidden();
    // operator manages the pipeline but cannot create complexes
    $this->actingAs(makeAdminRole('operator'), 'admin')->postJson("/admin/v1/applications/legal/{$legalId}/approve")->assertForbidden();
    expect(Complex::query()->count())->toBe(0);
});

it('legacy users (account_type NULL) keep working and can still apply', function () {
    $legacy = makeUser('+994500090081')->refresh();
    expect($legacy->getAttributes()['account_type'])->toBeNull();
    $this->actingAs($legacy, 'user')->getJson('/v1/me')->assertOk()->assertJsonPath('data.user.account_type', null);
    $fresh = User::query()->findOrFail($legacy->id);
    $fresh->forceFill(['email' => 'legacy@example.test', 'email_verified_at' => now()])->save();
    $this->actingAs($fresh->refresh(), 'user')->postJson('/v1/applications/individual', b9Individual())->assertCreated();
});
