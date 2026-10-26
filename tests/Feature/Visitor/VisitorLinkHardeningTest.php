<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Visitor\Models\VisitorLink;

/*
| O11 — the public /v/{token} page is rate limited per IP, and creating / revoking a visitor link leaves an
| audit row (token prefix only, never the token).
| O12 — an admin-created link must be able to open: active device, and on a private device someone on the
| roster who can open it; admins have their own active-link cap.
*/

it('rate limits the public visitor page per IP', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->get('/v/unknown-token-'.$i)->assertStatus(404);
    }

    $this->get('/v/unknown-token-61')->assertStatus(429);
});

it('audits a resident creating and revoking a visitor link', function () {
    $owner = makeUser('+994509700001');
    $device = makeOpenableDevice($owner, 'O11-1', '+994700970001');

    $res = $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(201);
    $link = VisitorLink::query()->findOrFail($res->json('link.id'));

    $created = AuditLog::query()->where('action', 'visitor_link.created')->sole();
    expect($created->actor_kind)->toBe('user')
        ->and((int) $created->actor_id)->toBe((int) $owner->id)
        ->and((int) $created->auditable_id)->toBe((int) $link->id)
        ->and($created->payload['device_id'])->toBe((int) $device->id)
        ->and($created->payload['access_type'])->toBe('one_time')
        ->and($created->payload['token_prefix'])->toBe($link->token_prefix)
        ->and(json_encode($created->payload))->not->toContain($res->json('token'));

    $this->actingAs($owner, 'user')->postJson("/v1/visitor-links/{$link->id}/revoke")->assertOk();
    $this->actingAs($owner, 'user')->postJson("/v1/visitor-links/{$link->id}/revoke")->assertOk(); // idempotent

    $revoked = AuditLog::query()->where('action', 'visitor_link.revoked')->sole();
    expect((int) $revoked->auditable_id)->toBe((int) $link->id)
        ->and($revoked->payload['device_id'])->toBe((int) $device->id);
});

it('audits an admin creating a link as the admin', function () {
    $admin = makeSuperAdmin('o11-admin@salamhayetimiz.az');
    $device = makeOpenableDevice(makeUser('+994509700002'), 'O11-2', '+994700970002');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(201);

    $row = AuditLog::query()->where('action', 'visitor_link.created')->sole();
    expect($row->actor_kind)->toBe('admin')->and((int) $row->actor_id)->toBe((int) $admin->id);
});

it('refuses an admin link for a device that is not active', function (string $status) {
    $admin = makeSuperAdmin('o12-status@salamhayetimiz.az');
    $device = makeOpenableDevice(makeUser('+994509700003'), 'O12-1', '+994700970003');
    $device->forceFill(['status' => $status])->save();

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'device_disabled');

    expect(VisitorLink::query()->count())->toBe(0);
})->with(['disabled', 'suspended', 'unassigned']);

it('refuses an admin link for a private device nobody on the roster can open', function () {
    $admin = makeSuperAdmin('o12-unpaid@salamhayetimiz.az');
    $device = makeOwnedDevice(makeUser('+994509700004'), 'O12-2', '+994700970004'); // owner without a subscription

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'subscription_required');
});

it('allows an admin link when another roster member still has access', function () {
    $admin = makeSuperAdmin('o12-member@salamhayetimiz.az');
    $device = makeOwnedDevice(makeUser('+994509700005'), 'O12-3', '+994700970005');
    $member = makeUser('+994509700006');
    makeSubscription(makeDeviceUser($member, $device, 'user'));

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(201);
});

it('allows an admin link on a complex device without any subscription', function () {
    $admin = makeSuperAdmin('o12-complex@salamhayetimiz.az');
    $complex = makeComplex('CX-O12', 'O12 Complex');
    $device = makeActiveDevice('O12-4', '+994700970007');
    $device->forceFill(['complex_id' => $complex->id, 'ownership_mode' => 'complex'])->save();

    expect(DeviceUser::query()->where('device_id', $device->id)->count())->toBe(0);

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(201);
});

it('caps the active links one admin can hold', function () {
    config(['domain.visitor.max_active_per_admin' => 2]);
    $admin = makeSuperAdmin('o12-cap@salamhayetimiz.az');
    $a = makeOpenableDevice(makeUser('+994509700008'), 'O12-5', '+994700970008');
    $b = makeOpenableDevice(makeUser('+994509700009'), 'O12-6', '+994700970009');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$a->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$b->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$b->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'visitor_link_limit_reached')
        ->assertJsonPath('error.details.scope', 'creator')
        ->assertJsonPath('error.details.limit', 2);
});
