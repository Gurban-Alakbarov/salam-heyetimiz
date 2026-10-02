<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\SubscriptionPeriod;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Bus;

/*
| IMPLEMENTATION_PLAN B11 — additive admin endpoints behind the admin UI: complex ↔ device binding, ownership
| mode switch (B4 invariants, 409s), sale price record, complex residents / invitations / users read models,
| Komendant link state, subscription detail (legacy comp + cancellation reason). RBAC + complex isolation.
*/

beforeEach(function () {
    Bus::fake([SendInvitationEmailJob::class]);
    $this->super = makeSuperAdmin();
    $this->cx = makeComplex('CX-B11', 'Kompleks B11');
    $this->other = makeComplex('CX-B11-O', 'Other');
});

function b11Device(string $serial, string $sim): Device
{
    return makeActiveDevice($serial, $sim)->refresh();
}

it('binds / unbinds a device and switches its mode with the B4 invariants (409s), audited', function () {
    $d = b11Device('SER-B11-1', '+994700110001');
    $as = $this->actingAs($this->super, 'admin');

    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertStatus(409)->assertJsonPath('error.code', 'device_not_in_complex');
    $as->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertOk()->assertJsonPath('complex_id', $this->cx->id)->assertJsonPath('ownership_mode', 'private');
    $as->postJson("/admin/v1/complexes/{$this->other->id}/devices/{$d->id}")->assertStatus(409)->assertJsonPath('error.code', 'device_in_other_complex');

    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertOk()->assertJsonPath('ownership_mode', 'complex');
    $as->deleteJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertStatus(409)->assertJsonPath('error.code', 'device_is_complex_mode');

    // an active complex roster + subscription blocks complex → private
    $resident = makeUser('+994500110001');
    app(ComplexMembershipService::class)->join($this->cx->id, $resident);
    $row = app(RosterService::class)->addMember($d->refresh(), $resident, DeviceUserRole::User, ActorKind::User, $resident->id);
    makeSubscription($row);
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'private'])->assertStatus(409)->assertJsonPath('error.code', 'device_has_roster_or_subscription');

    app(RosterService::class)->removeMember($d->refresh(), $resident, ActorKind::Admin, null);
    $row->refresh();
    \App\Domain\Subscriptions\Models\Subscription::query()->where('device_user_id', $row->id)->update(['status' => 'cancelled']);
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'private'])->assertOk()->assertJsonPath('ownership_mode', 'private');
    $as->deleteJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertOk()->assertJsonPath('complex_id', null);

    expect(AuditLog::query()->whereIn('action', ['complex.device_bound', 'complex.device_unbound', 'device.ownership_mode_changed'])->count())->toBe(4);
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'shared'])->assertStatus(422);
});

it('private → complex is refused while the device has an owner or an active roster', function () {
    $owned = makeOwnedDevice(makeUser('+994500110011'), 'SER-B11-2', '+994700110002');
    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$owned->id}")->assertOk();
    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$owned->id}/ownership-mode", ['ownership_mode' => 'complex'])
        ->assertStatus(409)->assertJsonPath('error.code', 'device_has_owner_or_roster');
    expect($owned->refresh()->ownership_mode->value)->toBe('private');
});

it('records the sale price as an admin note (who/when, audited) and exposes mode/complex/price on the device', function () {
    $d = b11Device('SER-B11-3', '+994700110003');
    $this->actingAs($this->super, 'admin')->patchJson("/admin/v1/devices/{$d->id}", ['sale_price_minor' => 20000])->assertOk()
        ->assertJsonPath('sale_price_minor', 20000)->assertJsonPath('ownership_mode', 'private')->assertJsonPath('complex_id', null);
    $d->refresh();
    expect((int) $d->sale_recorded_by_admin_id)->toBe($this->super->id)->and($d->sale_recorded_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'device.sale_price_recorded')->count())->toBe(1);
    $this->actingAs($this->super, 'admin')->patchJson("/admin/v1/devices/{$d->id}", ['sale_price_minor' => -5])->assertStatus(422);
    $this->actingAs($this->super, 'admin')->getJson("/admin/v1/devices/{$d->id}")->assertOk()->assertJsonPath('sale_price_minor', 20000);
});

it('complex detail carries coordinates and per-device mode; members and invitations are listed', function () {
    $this->cx->forceFill(['latitude' => 40.4012345, 'longitude' => 49.8512345])->save();
    $d = b11Device('SER-B11-4', '+994700110004');
    $d->forceFill(['complex_id' => $this->cx->id, 'ownership_mode' => 'complex'])->save();
    $resident = makeUser('+994500110021');
    $resident->forceFill(['email' => 'r@example.test', 'full_name' => 'Rəşad'])->save();
    app(ComplexMembershipService::class)->join($this->cx->id, $resident);
    makeSubscription(app(RosterService::class)->addMember($d->refresh(), $resident, DeviceUserRole::User, ActorKind::User, $resident->id));
    $manager = makeAdminRole('complex_manager', $this->cx->id);
    app(InvitationService::class)->createComplexResident($this->cx->id, $manager, 'new@example.test', 'Yeni', 'Sakin');

    $as = $this->actingAs($this->super, 'admin');
    $as->getJson("/admin/v1/complexes/{$this->cx->id}")->assertOk()
        ->assertJsonPath('latitude', 40.4012345)->assertJsonPath('longitude', 49.8512345)->assertJsonPath('devices.0.ownership_mode', 'complex');
    $as->getJson("/admin/v1/complexes/{$this->cx->id}/members")->assertOk()
        ->assertJsonPath('data.0.user_id', $resident->id)->assertJsonPath('data.0.active_subscriptions', 1)->assertJsonPath('data.0.phone', '+994500110021');
    $as->getJson("/admin/v1/invitations?complex_id={$this->cx->id}&status=pending")->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'new@example.test')->assertJsonPath('data.0.kind', 'complex_resident');
});

it('lists mobile users with account_type and the Komendant link state', function () {
    $legal = makeUser('+994500110031');
    $legal->forceFill(['account_type' => 'legal', 'email' => 'legal@example.test', 'email_verified_at' => now()])->save();
    makeUser('+994500110032');
    $mgr = makeAdminRole('complex_manager', $this->cx->id);
    $mgr->forceFill(['user_id' => $legal->id])->save();

    $as = $this->actingAs($this->super, 'admin');
    $rows = collect($as->getJson('/admin/v1/users?account_type=legal')->assertOk()->json('data'));
    expect($rows)->toHaveCount(1)->and($rows[0]['account_type'])->toBe('legal')->and($rows[0]['is_komendant_linked'])->toBeTrue();
    expect($as->getJson('/admin/v1/users?account_type=none')->json('meta.total'))->toBeGreaterThanOrEqual(1);

    $admins = collect($as->getJson('/admin/v1/admins')->assertOk()->json('data'))->keyBy('id');
    expect($admins[$mgr->id]['mobile_user']['email'])->toBe('legal@example.test')
        ->and($admins[$mgr->id]['user_id'])->toBe($legal->id)
        ->and($admins[$this->super->id]['mobile_user'])->toBeNull();
});

it('subscription detail: legacy comp flag, cancellation reason and payer ledger', function () {
    $user = makeUser('+994500110041');
    $device = makeOwnedDevice($user, 'SER-B11-5', '+994700110005');
    $row = \App\Domain\Roster\Models\DeviceUser::query()->where('device_id', $device->id)->firstOrFail();
    $comp = makeSubscription($row, ['price_minor' => 0, 'status' => SubscriptionStatus::Cancelled, 'cancellation_reason' => 'removed_by_komendant', 'cancelled_at' => now()]);

    $this->actingAs($this->super, 'admin')->getJson("/admin/v1/subscriptions/{$comp->id}")->assertOk()
        ->assertJsonPath('data.is_legacy_comp', true)->assertJsonPath('data.cancellation_reason', 'removed_by_komendant')
        ->assertJsonPath('data.device.ownership_mode', 'private')->assertJsonPath('data.beneficiary.id', $user->id);

    $comp->forceFill(['price_minor' => 1200])->save();
    SubscriptionPeriod::query()->create(['subscription_id' => $comp->id, 'order_id' => makePaidOrder($user, 1200, 'KB-B11-1')->id, 'kind' => 'initial',
        'period_start' => now(), 'period_end' => now()->addDays(30), 'amount_minor' => 1200, 'paid_by_user_id' => $user->id]);
    $this->actingAs($this->super, 'admin')->getJson("/admin/v1/subscriptions/{$comp->id}")
        ->assertJsonPath('data.is_legacy_comp', false)->assertJsonPath('data.periods.0.paid_by_user_id', $user->id);
});

it('RBAC + complex isolation on every new endpoint', function () {
    $this->getJson('/admin/v1/users')->assertStatus(401);

    $mine = b11Device('SER-B11-6', '+994700110006');
    $mine->forceFill(['complex_id' => $this->cx->id])->save();
    $theirs = b11Device('SER-B11-7', '+994700110007');
    $theirs->forceFill(['complex_id' => $this->other->id])->save();
    $insider = makeUser('+994500110051');
    app(ComplexMembershipService::class)->join($this->cx->id, $insider);
    $outsider = makeUser('+994500110052');
    app(ComplexMembershipService::class)->join($this->other->id, $outsider);
    $sub = makeSubscription(makeDeviceUser(makeUser('+994500110053'), $theirs, 'user'));

    $cm = makeAdminRole('complex_manager', $this->cx->id);
    $as = $this->actingAs($cm, 'admin');
    // complex_manager: no complexes.manage / devices.update
    $as->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$mine->id}")->assertForbidden();
    $as->postJson("/admin/v1/devices/{$mine->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertForbidden();
    // reads are locked to its own complex
    $member = $as->getJson("/admin/v1/complexes/{$this->cx->id}/members")->assertOk()->assertJsonPath('data.0.user_id', $insider->id)->json('data.0.phone');
    expect($member)->not->toBe('+994500110051')->and($member)->toContain('*'); // masked for a scoped manager
    $as->getJson("/admin/v1/complexes/{$this->other->id}/members")->assertNotFound();
    $ids = collect($as->getJson('/admin/v1/users')->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$insider->id]);
    expect($as->getJson("/admin/v1/invitations?complex_id={$this->other->id}")->assertOk()->json('data'))->toBe([]);

    // finance: subscriptions yes, people/devices no
    $fin = makeAdminRole('finance');
    $this->actingAs($fin, 'admin')->getJson('/admin/v1/users')->assertForbidden();
    $this->actingAs($fin, 'admin')->getJson("/admin/v1/subscriptions/{$sub->id}")->assertOk();
    $this->actingAs($fin, 'admin')->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$theirs->id}")->assertForbidden();
    // technical: devices.update yes, complexes.manage no
    $tech = makeAdminRole('technical');
    $this->actingAs($tech, 'admin')->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$theirs->id}")->assertForbidden();
    $this->actingAs($tech, 'admin')->postJson("/admin/v1/devices/{$mine->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertOk();
});
