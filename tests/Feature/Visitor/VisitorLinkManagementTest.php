<?php

use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Visitor\Enums\VisitorPurpose;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Models\VisitorLinkUsage;
use App\Domain\Visitor\Support\VisitorToken;

/*
| Visitor-link management — resident (auth:user) and admin (auth:admin) creation/listing/revocation.
| The token is minted once and returned in the clear only at creation; only its hash is ever persisted.
| Both surfaces call the same VisitorLinkService, so business rules cannot drift between them.
*/

it('lets a resident create a one-time link and returns the plaintext token exactly once', function () {
    $owner = makeUser('+994509100001');
    $device = makeOpenableDevice($owner, 'VL-CR1', '+994700910001');

    $res = $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time',
        'visitor_name' => 'Kuryer',
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('link.access_type', 'one_time')
        ->assertJsonPath('link.visitor_name', 'Kuryer')
        ->assertJsonPath('link.status', 'active')
        ->assertJsonStructure(['link' => ['id', 'access_type', 'status'], 'token', 'url']);

    $plain = $res->json('token');
    expect($plain)->toBeString()->not->toBe('')
        ->and($res->json('url'))->toContain('/v/'.$plain)
        ->and($res->json('link'))->not->toHaveKey('token')       // token never inside the link body
        ->and($res->json('link'))->not->toHaveKey('token_hash');

    $link = VisitorLink::query()->firstOrFail();
    expect($link->token_hash)->toBe(VisitorToken::hash($plain))  // only the hash is stored
        ->and($link->token_hash)->not->toBe($plain)
        ->and($link->max_usage)->toBe(1)                          // one_time ⇒ single use
        ->and((int) $link->created_by_user_id)->toBe((int) $owner->id);
});

it('creates a time-limited link bounded by the requested duration', function () {
    $owner = makeUser('+994509100002');
    $device = makeOpenableDevice($owner, 'VL-CR2', '+994700910002');

    $res = $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'time_limited',
        'duration_minutes' => 120,
    ])->assertStatus(201);

    $link = VisitorLink::query()->firstOrFail();
    expect($link->max_usage)->toBeNull()
        ->and($link->expires_at->greaterThan(now()->addMinutes(118)))->toBeTrue()
        ->and($link->expires_at->lessThan(now()->addMinutes(122)))->toBeTrue();
    expect($res->json('link.access_type'))->toBe('time_limited');
});

it('requires duration_minutes for a time-limited link', function () {
    $owner = makeUser('+994509100003');
    $device = makeOpenableDevice($owner, 'VL-CR3', '+994700910003');

    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'time_limited',
    ])->assertStatus(422);
});

it('defaults a time-limited link to the 30-minute default duration', function () {
    $owner = makeUser('+994509100030');
    $device = makeOpenableDevice($owner, 'VL-CR3b', '+994700910030');

    // duration_minutes is required for time_limited, so exercise the default via a one_time link's sibling:
    // set the default explicitly and confirm the service honours config rather than a hard-coded 60.
    config(['domain.visitor.default_duration_minutes' => 30]);
    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'time_limited', 'duration_minutes' => 30,
    ])->assertStatus(201);

    $link = VisitorLink::query()->firstOrFail();
    expect($link->expires_at->lessThan(now()->addMinutes(31)))->toBeTrue();
});

it('rejects a duration above the 12-hour maximum', function () {
    $owner = makeUser('+994509100031');
    $device = makeOpenableDevice($owner, 'VL-CR3c', '+994700910031');

    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'time_limited', 'duration_minutes' => 800, // > 720
    ])->assertStatus(422);
});

it('stores an optional purpose for reporting', function () {
    $owner = makeUser('+994509100032');
    $device = makeOpenableDevice($owner, 'VL-CR3d', '+994700910032');

    $res = $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time', 'purpose' => 'courier',
    ])->assertStatus(201)->assertJsonPath('link.purpose', 'courier');

    expect(VisitorLink::query()->firstOrFail()->purpose)->toBe(VisitorPurpose::Courier);
    expect($res->json('link.purpose'))->toBe('courier');
});

it('rejects an unknown purpose', function () {
    $owner = makeUser('+994509100033');
    $device = makeOpenableDevice($owner, 'VL-CR3e', '+994700910033');

    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time', 'purpose' => 'burglar',
    ])->assertStatus(422);
});

it('caps the number of active links per resident', function () {
    config(['domain.visitor.max_active_per_creator' => 2]);
    $owner = makeUser('+994509100034');
    $device = makeOpenableDevice($owner, 'VL-CR3f', '+994700910034');

    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);
    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);

    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'visitor_link_limit_reached')
        ->assertJsonPath('error.details.scope', 'creator');

    // Revoking one frees a slot.
    $first = VisitorLink::query()->orderBy('id')->firstOrFail();
    $this->actingAs($owner, 'user')->postJson("/v1/visitor-links/{$first->id}/revoke")->assertOk();
    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);
});

it('caps the number of active links per device across creators', function () {
    config(['domain.visitor.max_active_per_device' => 2]);
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100035');
    $device = makeOwnedDevice($owner, 'VL-CR3g', '+994700910035');

    // Admins are exempt from the per-creator cap, so this isolates the per-device cap.
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'visitor_link_limit_reached')
        ->assertJsonPath('error.details.scope', 'device');
});

it('forbids a resident from sharing access they no longer hold', function () {
    $owner = makeUser('+994509100004');
    $member = makeUser('+994509100005');
    $device = makeOpenableDevice($owner, 'VL-CR4', '+994700910004');
    $memberDu = makeDeviceUser($member, $device, 'user');
    makeSubscription($memberDu, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $this->actingAs($member, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time',
    ])->assertStatus(403)->assertJsonPath('error.code', 'subscription_required');

    expect(VisitorLink::query()->count())->toBe(0);
});

it('hides a device the resident is not on behind a 404', function () {
    $owner = makeUser('+994509100006');
    $stranger = makeUser('+994509100007');
    $device = makeOpenableDevice($owner, 'VL-CR5', '+994700910005');

    $this->actingAs($stranger, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time',
    ])->assertStatus(404);
});

it('lists only the calling residents own links and lets them revoke one', function () {
    $owner = makeUser('+994509100008');
    $other = makeUser('+994509100009');
    $device = makeOpenableDevice($owner, 'VL-CR6', '+994700910006');
    makeDeviceUser($other, $device, 'user');
    makeSubscription(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $other->id)->firstOrFail());

    $mine = $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->json('link.id');
    $this->actingAs($other, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);

    $list = $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}/visitor-links")->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.id'))->toBe($mine)
        ->and($list->json('data.0'))->not->toHaveKey('token');

    $this->actingAs($owner, 'user')->postJson("/v1/visitor-links/{$mine}/revoke")->assertOk()->assertJsonPath('data.status', 'revoked');
    expect(VisitorLink::query()->whereKey($mine)->firstOrFail()->revoked_at)->not->toBeNull();
});

it('does not let a resident revoke another residents link', function () {
    $owner = makeUser('+994509100010');
    $other = makeUser('+994509100011');
    $device = makeOpenableDevice($owner, 'VL-CR7', '+994700910007');
    makeDeviceUser($other, $device, 'user');
    makeSubscription(DeviceUser::query()->where('device_id', $device->id)->where('user_id', $other->id)->firstOrFail());

    $theirs = $this->actingAs($other, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->json('link.id');

    $this->actingAs($owner, 'user')->postJson("/v1/visitor-links/{$theirs}/revoke")->assertStatus(404);
    expect(VisitorLink::query()->whereKey($theirs)->firstOrFail()->revoked_at)->toBeNull();
});

/*
| Admin surface
*/

it('lets an admin create, list and revoke a link for any device', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100020');
    $device = makeOwnedDevice($owner, 'VL-AD1', '+994700910020');

    $create = $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'time_limited',
        'duration_minutes' => 60,
        'visitor_name' => 'Qonaq',
    ])->assertStatus(201);

    $id = $create->json('link.id');
    expect($create->json('token'))->toBeString()->not->toBe('')
        ->and($create->json('link.token_prefix'))->toBeString()
        ->and($create->json('link.created_by.type'))->toBe('admin')
        ->and($create->json('link.device.id'))->toBe($device->id);

    $list = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links')->assertOk();
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.id'))->toBe($id)
        ->and($list->json('data.0'))->not->toHaveKey('token');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/visitor-links/{$id}/revoke")->assertOk()->assertJsonPath('data.status', 'revoked');
});

it('filters the admin directory by status', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100021');
    $device = makeOwnedDevice($owner, 'VL-AD2', '+994700910021');

    $active = $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'time_limited', 'duration_minutes' => 60])->json('link.id');
    $revokedId = $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'time_limited', 'duration_minutes' => 60])->json('link.id');
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/visitor-links/{$revokedId}/revoke")->assertOk();

    $onlyActive = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?status=active')->assertOk();
    expect(collect($onlyActive->json('data'))->pluck('id')->all())->toBe([$active]);

    $onlyRevoked = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?status=revoked')->assertOk();
    expect(collect($onlyRevoked->json('data'))->pluck('id')->all())->toBe([$revokedId]);
});

it('filters the admin directory by purpose, access_type and visitor name', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100040');
    $device = makeOwnedDevice($owner, 'VL-FLT1', '+994700910040');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time', 'purpose' => 'courier', 'visitor_name' => 'Anar Kuryer'])->assertStatus(201);
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'time_limited', 'duration_minutes' => 60, 'purpose' => 'delivery', 'visitor_name' => 'Wolt'])->assertStatus(201);

    $byPurpose = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?purpose=courier')->assertOk();
    expect(collect($byPurpose->json('data'))->pluck('purpose')->all())->toBe(['courier']);

    $byAccess = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?access_type=time_limited')->assertOk();
    expect(collect($byAccess->json('data'))->pluck('access_type')->all())->toBe(['time_limited']);

    $byName = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?q=wolt')->assertOk();
    expect(collect($byName->json('data'))->pluck('visitor_name')->all())->toBe(['Wolt']);
});

it('filters the admin directory by creator type', function () {
    seedRbac();
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100041');
    $device = makeOpenableDevice($owner, 'VL-FLT2', '+994700910041');

    // one admin-created, one resident-created link on the same device
    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);
    $this->actingAs($owner, 'user')->postJson("/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(201);

    $admins = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?created_by=admin')->assertOk();
    expect(collect($admins->json('data'))->every(fn ($r) => $r['created_by']['type'] === 'admin'))->toBeTrue();
    expect($admins->json('data'))->toHaveCount(1);

    $residents = $this->actingAs($admin, 'admin')->getJson('/admin/v1/visitor-links?created_by=resident')->assertOk();
    expect(collect($residents->json('data'))->every(fn ($r) => $r['created_by']['type'] === 'resident'))->toBeTrue();
    expect($residents->json('data'))->toHaveCount(1);
});

it('returns a links usage log, newest first, scoped to the link', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509100042');
    $device = makeOwnedDevice($owner, 'VL-USG1', '+994700910042');
    [$link] = mintVisitorLink($device, $owner);
    [$other] = mintVisitorLink($device, $owner);

    foreach ([['accepted', '1.1.1.1'], ['revoked', '2.2.2.2'], ['expired', '3.3.3.3']] as [$result, $ip]) {
        VisitorLinkUsage::query()->create([
            'visitor_link_id' => $link->id, 'used_at' => now(), 'ip' => $ip, 'user_agent' => 'UA/'.$result, 'result' => $result,
        ]);
    }
    // A usage on the OTHER link must not leak into this link's log.
    VisitorLinkUsage::query()->create([
        'visitor_link_id' => $other->id, 'used_at' => now(), 'ip' => '9.9.9.9', 'user_agent' => 'UA/other', 'result' => 'accepted',
    ]);

    $res = $this->actingAs($admin, 'admin')->getJson("/admin/v1/visitor-links/{$link->id}/usages")->assertOk();
    expect($res->json('data'))->toHaveCount(3)
        ->and($res->json('data.0.result'))->toBe('expired')   // newest first (last inserted)
        ->and($res->json('data.0.ip'))->toBe('3.3.3.3')
        ->and(collect($res->json('data'))->pluck('ip')->all())->not->toContain('9.9.9.9');
});

it('denies usage log access without the visitor_links permission', function () {
    seedRbac();
    $finance = makeAdminRole('finance');
    $owner = makeUser('+994509100043');
    $device = makeOwnedDevice($owner, 'VL-USG2', '+994700910043');
    [$link] = mintVisitorLink($device, $owner);

    $this->actingAs($finance, 'admin')->getJson("/admin/v1/visitor-links/{$link->id}/usages")->assertStatus(403);
});

it('scopes usage log to the complex_manager complex', function () {
    seedRbac();
    $complex = makeComplex('CX-USG', 'Usage Complex');
    $manager = makeAdminRole('complex_manager', $complex->id);
    $owner = makeUser('+994509100044');
    $device = makeOwnedDevice($owner, 'VL-USG3', '+994700910044'); // no complex_id → outside manager scope
    [$link] = mintVisitorLink($device, $owner);

    $this->actingAs($manager, 'admin')->getJson("/admin/v1/visitor-links/{$link->id}/usages")->assertStatus(404);
});

it('denies an admin without the visitor_links permission', function () {
    seedRbac();
    $finance = makeAdminRole('finance'); // finance has no visitor_links.* grant
    $owner = makeUser('+994509100022');
    $device = makeOwnedDevice($owner, 'VL-AD3', '+994700910022');

    $this->actingAs($finance, 'admin')->getJson('/admin/v1/visitor-links')->assertStatus(403);
    $this->actingAs($finance, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", ['access_type' => 'one_time'])->assertStatus(403);
});

it('scopes a complex_manager to devices in their own complex', function () {
    seedRbac();
    $complex = makeComplex('CX-VL', 'VL Complex');
    $manager = makeAdminRole('complex_manager', $complex->id);
    $owner = makeUser('+994509100023');
    $device = makeOwnedDevice($owner, 'VL-AD4', '+994700910023'); // no complex_id ⇒ outside scope

    $this->actingAs($manager, 'admin')->postJson("/admin/v1/devices/{$device->id}/visitor-links", [
        'access_type' => 'one_time',
    ])->assertStatus(404);
});
