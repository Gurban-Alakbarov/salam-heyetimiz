<?php

use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Users\Models\ResidentArchive;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;

/*
| O10 — an admin's "delete resident" never loses the person: a global admin deletes the account but it is first
| archived (resident_archives, 5 years, then purged); a complex_manager only removes the resident from ITS
| complex, exactly like the Komendant app. Self-service deletion stays erased (not archived).
*/

beforeEach(function () {
    Bus::fake();
    $this->cx = makeComplex('CX-O10', 'Kompleks O10');
    $this->gate = makeActiveDevice('SER-O10-1', '+994700100001');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
});

function o10Resident(string $phone, string $email, $test): User
{
    $user = makeUser($phone);
    $user->forceFill(['full_name' => 'Arxiv Sakin', 'email' => $email, 'email_verified_at' => now()])->save();
    app(ComplexMembershipService::class)->join($test->cx->id, $user);
    makeSubscription(makeDeviceUser($user, $test->gate, 'user'), ['ends_at' => now()->addDays(40)]);

    return $user->refresh();
}

it('archives a resident a global admin deletes, then anonymises the account', function () {
    $admin = makeSuperAdmin('o10-super@salamhayetimiz.az');
    $user = o10Resident('+994501000101', 'arxiv@example.test', $this);

    $this->actingAs($admin, 'admin')->deleteJson("/admin/v1/residents/{$user->id}")->assertNoContent();

    $row = ResidentArchive::query()->sole();
    expect($row->user_id)->toBe((int) $user->id)
        ->and($row->phone)->toBe('+994501000101')
        ->and($row->email)->toBe('arxiv@example.test')
        ->and($row->full_name)->toBe('Arxiv Sakin')
        ->and((int) $row->deleted_by_admin_id)->toBe((int) $admin->id)
        ->and($row->reason)->toBe('admin_deleted')
        ->and($row->purge_after->year)->toBe(now()->addYears(5)->year)
        ->and($row->snapshot['devices'])->toHaveCount(1)
        ->and($row->snapshot['devices'][0]['serial'])->toBe('SER-O10-1')
        ->and($row->snapshot['devices'][0]['complex']['name'])->toBe('Kompleks O10')
        ->and($row->snapshot['devices'][0]['subscription']['status'])->toBe('active') // as it was, before the cancel
        ->and($row->snapshot['complexes'][0]['name'])->toBe('Kompleks O10');

    $ghost = User::withTrashed()->findOrFail($user->id);
    expect($ghost->trashed())->toBeTrue()->and($ghost->phone)->toBe('del:'.$user->id)->and($ghost->email)->toBeNull();
});

it('does not archive an account its owner deletes from the app', function () {
    $tokens = authenticateViaOtp('+994501000102');

    $this->withHeaders(bearer($tokens['access_token']))->deleteJson('/v1/me', ['confirm' => true])->assertNoContent();

    expect(ResidentArchive::query()->count())->toBe(0);
});

it('lets a complex_manager only remove the resident from its own complex — the account stays', function () {
    $manager = makeAdminRole('complex_manager', $this->cx->id);
    $user = o10Resident('+994501000103', 'qalir@example.test', $this);
    $private = makeOpenableDevice($user, 'SER-O10-PRV', '+994700100002'); // their own gate elsewhere

    $this->actingAs($manager, 'admin')->deleteJson("/admin/v1/residents/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.removed_from', 'complex')
        ->assertJsonPath('data.complex_id', $this->cx->id)
        ->assertJsonPath('data.revoked_rows', 1)
        ->assertJsonPath('data.cancelled_subscriptions', 1);

    $fresh = User::query()->find($user->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->phone)->toBe('+994501000103')
        ->and(ComplexMember::query()->where('user_id', $user->id)->where('status', 'active')->exists())->toBeFalse()
        ->and(DeviceUser::query()->where('device_id', $this->gate->id)->where('user_id', $user->id)->value('status')->value)->toBe('revoked')
        ->and(DeviceUser::query()->where('device_id', $private->id)->where('user_id', $user->id)->value('status')->value)->toBe('active')
        ->and(ResidentArchive::query()->count())->toBe(0);
});

it('answers 404 when the complex_manager\'s complex does not have this resident', function () {
    $manager = makeAdminRole('complex_manager', $this->cx->id);
    $stranger = makeUser('+994501000104');

    $this->actingAs($manager, 'admin')->deleteJson("/admin/v1/residents/{$stranger->id}")->assertNotFound();
    expect(User::query()->find($stranger->id))->not->toBeNull();
});

it('shows the archive to super_admin only (search by phone)', function () {
    seedRbac();
    $super = makeSuperAdmin('o10-viewer@salamhayetimiz.az');
    $a = o10Resident('+994501000105', 'birinci@example.test', $this);
    $this->actingAs($super, 'admin')->deleteJson("/admin/v1/residents/{$a->id}")->assertNoContent();

    $this->actingAs($super, 'admin')->getJson('/admin/v1/resident-archive?q=000105')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.phone', '+994501000105')
        ->assertJsonPath('data.0.deleted_by.email', 'o10-viewer@salamhayetimiz.az')
        ->assertJsonPath('data.0.snapshot.devices.0.serial', 'SER-O10-1');
    $this->actingAs($super, 'admin')->getJson('/admin/v1/resident-archive?q=yoxdur')->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs(makeAdminRole('operator'), 'admin')->getJson('/admin/v1/resident-archive')->assertForbidden();
    $this->actingAs(makeAdminRole('complex_manager', $this->cx->id), 'admin')->getJson('/admin/v1/resident-archive')->assertForbidden();
});

it('purges archive rows past their retention and keeps the rest', function () {
    $old = ResidentArchive::query()->create(['user_id' => 1, 'phone' => '+994500000001', 'reason' => 'admin_deleted', 'archived_at' => now()->subYears(5)->subDay(), 'purge_after' => now()->subDay()]);
    $kept = ResidentArchive::query()->create(['user_id' => 2, 'phone' => '+994500000002', 'reason' => 'admin_deleted', 'archived_at' => now()->subYear(), 'purge_after' => now()->addYears(4)]);

    $this->artisan('residents:purge-archive', ['--dry-run' => true])->expectsOutputToContain('would purge 1')->assertSuccessful();
    expect(ResidentArchive::query()->count())->toBe(2);

    $this->artisan('residents:purge-archive')->assertSuccessful();
    expect(ResidentArchive::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(ResidentArchive::query()->find($old->id))->toBeNull();
});

it('archives legacy deletions before the backfill anonymises them', function () {
    $legacy = makeUser('+994501000106');
    $legacy->forceFill(['email' => 'kohne@example.test', 'full_name' => 'Köhnə Sakin'])->save();
    $legacy->delete(); // soft-deleted before deletions anonymised

    $this->artisan('users:anonymise-deleted')->assertSuccessful();

    $row = ResidentArchive::query()->sole();
    expect($row->reason)->toBe('legacy_deleted')
        ->and($row->phone)->toBe('+994501000106')
        ->and($row->email)->toBe('kohne@example.test')
        ->and($row->deleted_by_admin_id)->toBeNull()
        ->and(User::withTrashed()->find($legacy->id)->phone)->toBe('del:'.$legacy->id);
});
