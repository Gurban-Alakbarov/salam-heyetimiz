<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Users\Models\User;

/*
| K5 — a complex-mode device has no owner by design (B4), so its in-service state can no longer come from an
| owner assignment: switching an owner-less device to complex mode puts it in service (active), re-enabling
| keeps it active, and switching back to private (still owner-less) returns it to unassigned. Disabled is
| never overridden by a mode switch. Uses a real `unassigned` device — no forceFill of the status.
*/

beforeEach(function () {
    $this->super = makeSuperAdmin();
    $this->cx = makeComplex('CX-K5', 'Kompleks K5');
});

function k5Resident(int $complexId, string $phone): User
{
    $u = makeUser($phone);
    app(ComplexMembershipService::class)->join($complexId, $u);

    return $u->refresh();
}

it('a fresh unassigned device becomes active when switched to complex mode, and residents can subscribe', function () {
    $d = makeUnassignedDevice('SER-K5-1', '+994700550001');
    $as = $this->actingAs($this->super, 'admin');

    $as->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertOk()->assertJsonPath('status', 'unassigned');
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertOk()
        ->assertJsonPath('ownership_mode', 'complex')->assertJsonPath('status', 'active');

    $d->refresh();
    expect($d->status)->toBe(DeviceStatus::Active)
        ->and($d->owner_user_id)->toBeNull()
        ->and($d->activated_at)->not->toBeNull();
    $audit = AuditLog::query()->where('action', 'device.ownership_mode_changed')->latest('id')->first();
    expect($audit->payload['status_from'])->toBe('unassigned')->and($audit->payload['status_to'])->toBe('active');

    $resident = k5Resident($this->cx->id, '+994500550001');
    $mobile = $this->withHeaders(bearer(userAccessToken($resident)));
    $mobile->getJson("/v1/complexes/{$this->cx->id}/devices")->assertOk()
        ->assertJsonPath('data.0.id', $d->id)->assertJsonPath('data.0.my_subscription_status', 'none');
    $mobile->postJson("/v1/complexes/{$this->cx->id}/devices/{$d->id}/subscribe", [], ['Idempotency-Key' => 'k5-sub-1'])
        ->assertCreated();
});

it('disable + enable keeps a complex-mode device active (no longer falls back to unassigned)', function () {
    $d = makeUnassignedDevice('SER-K5-2', '+994700550002');
    $as = $this->actingAs($this->super, 'admin');
    $as->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertOk();
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertOk();

    $as->postJson("/admin/v1/devices/{$d->id}/disable", ['reason' => 'servis'])->assertOk()->assertJsonPath('status', 'disabled');
    $as->postJson("/admin/v1/devices/{$d->id}/enable")->assertOk()->assertJsonPath('status', 'active');
    expect($d->refresh()->status)->toBe(DeviceStatus::Active);
});

it('a mode switch never overrides disabled; complex → private without an owner returns to unassigned', function () {
    $d = makeUnassignedDevice('SER-K5-3', '+994700550003');
    $as = $this->actingAs($this->super, 'admin');
    $as->postJson("/admin/v1/complexes/{$this->cx->id}/devices/{$d->id}")->assertOk();

    $as->postJson("/admin/v1/devices/{$d->id}/disable", ['reason' => 'servis'])->assertOk();
    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'complex'])->assertOk()
        ->assertJsonPath('status', 'disabled');
    $as->postJson("/admin/v1/devices/{$d->id}/enable")->assertOk()->assertJsonPath('status', 'active');

    $as->postJson("/admin/v1/devices/{$d->id}/ownership-mode", ['ownership_mode' => 'private'])->assertOk()
        ->assertJsonPath('ownership_mode', 'private')->assertJsonPath('status', 'unassigned');
});

it('a private device with an owner keeps its status and enable still restores it to active', function () {
    $d = makeOwnedDevice(makeUser('+994500550004'), 'SER-K5-4', '+994700550004');
    $as = $this->actingAs($this->super, 'admin');
    $as->postJson("/admin/v1/devices/{$d->id}/disable", ['reason' => 'servis'])->assertOk();
    $as->postJson("/admin/v1/devices/{$d->id}/enable")->assertOk()->assertJsonPath('status', 'active');

    $fresh = makeUnassignedDevice('SER-K5-5', '+994700550005');
    $as->postJson("/admin/v1/devices/{$fresh->id}/disable", ['reason' => 'servis'])->assertOk();
    $as->postJson("/admin/v1/devices/{$fresh->id}/enable")->assertOk()->assertJsonPath('status', 'unassigned');
});
