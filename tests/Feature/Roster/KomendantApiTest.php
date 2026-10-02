<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPeriod;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;

/*
| IMPLEMENTATION_PLAN B5 — the Komendant mobile surface: a mobile user linked to an active complex_manager,
| hard-scoped to that complex. Removal revokes access + cancels subscriptions (no refund), audited.
*/

function b5Device(int $complexId, string $serial, string $sim): Device
{
    $device = makeActiveDevice($serial, $sim);
    $device->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complexId, 'owner_user_id' => null])->save();

    return $device->refresh();
}

function b5Komendant(int $complexId, string $phone, string $status = 'active'): array
{
    $user = makeUser($phone);
    $user->forceFill(['email' => 'k'.substr($phone, -4).'@example.test', 'email_verified_at' => now()])->save();
    $manager = makeAdminRole('complex_manager', $complexId);
    $manager->forceFill(['user_id' => $user->id, 'status' => $status])->save();

    return [$user->refresh(), $manager->refresh()];
}

beforeEach(function () {
    Bus::fake();
    $this->cx = makeComplex('CX-B5', 'Complex B5');
    $this->other = makeComplex('CX-B5-O', 'Other B5');
    [$this->kUser, $this->manager] = b5Komendant($this->cx->id, '+994500050001');
    $this->members = app(ComplexMembershipService::class);
});

it('rejects unlinked, non-manager-linked and suspended Komendant accounts', function () {
    $this->getJson('/v1/komendant/complex')->assertStatus(401);
    $this->actingAs(makeUser('+994500050002'), 'user')->getJson('/v1/komendant/complex')
        ->assertStatus(403)->assertJsonPath('error.code', 'not_komendant');

    $linkedFinance = makeUser('+994500050003');
    makeAdminRole('finance')->forceFill(['user_id' => $linkedFinance->id])->save();
    $this->actingAs($linkedFinance, 'user')->getJson('/v1/komendant/complex')->assertStatus(403);

    [$suspended] = b5Komendant($this->cx->id, '+994500050004', 'suspended');
    $this->actingAs($suspended, 'user')->getJson('/v1/komendant/complex')->assertStatus(403);

});

it('shows only its own complex: devices (subscription price, never sale price), residents, stats', function () {
    $mine = b5Device($this->cx->id, 'SER-B5-1', '+994700050001');
    $mine->forceFill(['sale_price_minor' => 13500])->save();
    b5Device($this->other->id, 'SER-B5-2', '+994700050002');
    makeOwnedDevice(makeUser('+994500050010'), 'SER-B5-3', '+994700050003')->forceFill(['complex_id' => $this->cx->id])->save(); // private — not listed

    $resident = makeUser('+994500050011');
    $this->members->join($this->cx->id, $resident);
    $this->members->join($this->other->id, makeUser('+994500050012'));

    $as = $this->actingAs($this->kUser, 'user');
    $as->getJson('/v1/komendant/complex')->assertOk()
        ->assertJsonPath('data.id', $this->cx->id)
        ->assertJsonPath('data.stats.devices', 1)
        ->assertJsonPath('data.stats.residents', 1);

    $devices = $as->getJson('/v1/komendant/devices')->assertOk()->json('data');
    expect($devices)->toHaveCount(1)
        ->and($devices[0]['id'])->toBe($mine->id)
        ->and($devices[0]['subscription_price_minor'])->toBe(1200)
        ->and($devices[0]['subscription_term_days'])->toBe(30)
        ->and($devices[0])->not->toHaveKey('sale_price_minor');

    $residents = $as->getJson('/v1/komendant/residents')->assertOk()->json('data');
    expect($residents)->toHaveCount(1)->and($residents[0]['user_id'])->toBe($resident->id);
});

it('invites, lists, resends and revokes — and other complexes\' invitations answer 404', function () {
    $as = $this->actingAs($this->kUser, 'user');
    $id = $as->postJson('/v1/komendant/invitations', ['first_name' => 'Aysel', 'last_name' => 'M', 'email' => 'Aysel@Example.test'])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.email', 'aysel@example.test')->json('data.id');
    expect(Invitation::query()->find($id)->complex_id)->toBe($this->cx->id);

    $as->postJson('/v1/komendant/invitations', ['first_name' => 'A', 'last_name' => 'M', 'email' => 'aysel@example.test'])->assertStatus(409);
    expect($as->getJson('/v1/komendant/invitations?status=pending')->json('data'))->toHaveCount(1);

    $this->travel(61)->seconds();
    $as->postJson("/v1/komendant/invitations/{$id}/resend")->assertOk()->assertJsonPath('data.send_count', 2);
    $as->postJson("/v1/komendant/invitations/{$id}/revoke")->assertOk()->assertJsonPath('data.status', 'cancelled');

    [$otherK] = b5Komendant($this->other->id, '+994500050020');
    $this->actingAs($otherK, 'user')->postJson("/v1/komendant/invitations/{$id}/revoke")->assertNotFound();
    $this->actingAs($otherK, 'user')->postJson("/v1/komendant/invitations/{$id}/resend")->assertNotFound();
    expect($this->actingAs($otherK, 'user')->getJson('/v1/komendant/invitations')->json('data'))->toBe([]);
});

it('refuses to invite an existing resident', function () {
    $resident = makeUser('+994500050030');
    $resident->forceFill(['email' => 'res@example.test'])->save();
    $this->members->join($this->cx->id, $resident);

    $this->actingAs($this->kUser, 'user')
        ->postJson('/v1/komendant/invitations', ['first_name' => 'R', 'last_name' => 'S', 'email' => 'res@example.test'])
        ->assertStatus(409)->assertJsonPath('error.code', 'already_resident');
});

it('removal revokes the resident + their family rows, cancels subscriptions without refund, keeps paid history', function () {
    $device = b5Device($this->cx->id, 'SER-B5-R1', '+994700050031');
    $private = makeOwnedDevice($resident = makeUser('+994500050031'), 'SER-B5-R2', '+994700050032');
    $private->forceFill(['complex_id' => $this->cx->id])->save();
    $this->members->join($this->cx->id, $resident);

    $roster = app(RosterService::class);
    $own = $roster->addMember($device, $resident, DeviceUserRole::User, ActorKind::Admin, null);
    $family = makeUser('+994500050032');
    $familyRow = $roster->addMember($device, $family, DeviceUserRole::User, ActorKind::User, $resident->id, 501);
    $sub = makeSubscription($own);
    SubscriptionPeriod::query()->create([
        'subscription_id' => $sub->id, 'order_id' => makePaidOrder($resident, 1200, 'KB-B5-1')->id, 'kind' => 'initial',
        'period_start' => now()->subDay(), 'period_end' => now()->addDays(29), 'amount_minor' => 1200, 'paid_by_user_id' => $resident->id,
    ]);
    makeSubscription($familyRow, ['status' => SubscriptionStatus::PendingPayment]);

    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$resident->id}")
        ->assertOk()->assertJsonPath('data.revoked_rows', 2)->assertJsonPath('data.cancelled_subscriptions', 2);

    expect($this->members->isMember($this->cx->id, $resident->id))->toBeFalse()
        ->and($own->refresh()->status)->not->toBe(DeviceUserStatus::Active)
        ->and($familyRow->refresh()->status)->not->toBe(DeviceUserStatus::Active)
        ->and($sub->refresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(SubscriptionPeriod::query()->where('subscription_id', $sub->id)->count())->toBe(1)
        ->and(DeviceUser::query()->where('device_id', $private->id)->where('user_id', $resident->id)->first()?->status)->toBe(DeviceUserStatus::Active);

    $audits = AuditLog::query()->where('action', 'subscription.cancelled_on_removal')->get();
    expect($audits)->toHaveCount(2)->and($audits->first()->payload['refund'] ?? null)->toBe('none');
    expect(AuditLog::query()->where('action', 'complex.resident_removed')->count())->toBe(1);

    // not a resident (any more / of another complex) → 404
    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$resident->id}")->assertNotFound();
});

it('exposes no free-subscription or pricing path to a Komendant', function () {
    $paths = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'v1/komendant'))
        ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())->values()->all();

    expect($paths)->toHaveCount(8);
    foreach ($paths as $p) {
        expect($p)->not->toContain('subscription')->not->toContain('price')->not->toContain('grant');
    }
    expect(Subscription::query()->count())->toBe(0);
});

it('admin links and unlinks a complex_manager to a verified mobile account (admins.update, audited)', function () {
    $target = makeAdminRole('complex_manager', $this->cx->id);
    $mobile = makeUser('+994500050040');
    $mobile->forceFill(['email' => 'mgr@example.test', 'email_verified_at' => now()])->save();
    $mobile->refresh();
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')->postJson("/admin/v1/admins/{$target->id}/mobile-user", ['email' => 'MGR@example.test'])
        ->assertOk()->assertJsonPath('data.mobile_user.id', $mobile->id);
    expect((int) $target->refresh()->user_id)->toBe($mobile->id);
    $this->actingAs($mobile, 'user')->getJson('/v1/me')->assertOk()
        ->assertJsonPath('data.roles', ['resident', 'komendant'])
        ->assertJsonPath('data.komendant.complex.id', $this->cx->id);

    // the same mobile account cannot back a second admin
    $second = makeAdminRole('complex_manager', $this->other->id);
    $this->actingAs($super, 'admin')->postJson("/admin/v1/admins/{$second->id}/mobile-user", ['email' => 'mgr@example.test'])->assertStatus(409);
    // only complex managers; only verified accounts
    $this->actingAs($super, 'admin')->postJson('/admin/v1/admins/'.makeAdminRole('finance')->id.'/mobile-user', ['email' => 'mgr@example.test'])->assertStatus(422);
    makeUser('+994500050041')->forceFill(['email' => 'unverified@example.test'])->save();
    $this->actingAs($super, 'admin')->postJson("/admin/v1/admins/{$second->id}/mobile-user", ['email' => 'unverified@example.test'])->assertStatus(422);
    // permission gate
    $this->actingAs(makeAdminRole('finance'), 'admin')->postJson("/admin/v1/admins/{$target->id}/mobile-user", ['email' => 'mgr@example.test'])->assertStatus(403);

    $this->actingAs($super, 'admin')->deleteJson("/admin/v1/admins/{$target->id}/mobile-user")->assertOk();
    expect($target->refresh()->user_id)->toBeNull();
    $this->actingAs($mobile, 'user')->getJson('/v1/me')->assertJsonPath('data.roles', ['resident'])->assertJsonPath('data.komendant', null);
    expect(AuditLog::query()->whereIn('action', ['admin.mobile_user_linked', 'admin.mobile_user_unlinked'])->count())->toBe(2);
});

it('lists the complexes the user is a resident of in /v1/me', function () {
    $u = makeUser('+994500050050')->refresh();
    $this->members->join($this->cx->id, $u);
    $this->actingAs($u, 'user')->getJson('/v1/me')->assertOk()
        ->assertJsonPath('data.complexes', [['id' => $this->cx->id, 'name' => 'Complex B5']])
        ->assertJsonPath('data.roles', ['resident']);
});

it('has no demo-account literal in application code (BR-21)', function () {
    $hits = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $src = file_get_contents($file->getPathname());
            if (preg_match('/komendant@|demo@|demo[-_.]?komendant/i', $src)) {
                $hits[] = $file->getPathname();
            }
        }
    }
    expect($hits)->toBe([]);
});
