<?php

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Devices\Models\Device;

/*
| RBAC — permission resolution, per-endpoint enforcement (403), complex scoping, impersonation, audit,
| and the /me permissions payload. Enforcement works via the code-matrix fallback when the permission
| tables are unseeded; one test seeds them to prove the DB-driven path matches.
*/

// ---- permission resolution (model) ----

it('resolves super_admin to every permission', function () {
    expect(makeSuperAdmin()->hasPermission(Permission::SYSTEM_SETTINGS_MANAGE))->toBeTrue()
        ->and(makeSuperAdmin('s2@salamhayetimiz.az')->hasPermission(Permission::DEVICES_DECOMMISSION))->toBeTrue();
});

it('grants each role exactly its matrix permissions', function () {
    expect(makeAdminRole('technical')->hasPermission(Permission::DEVICES_CREATE))->toBeTrue()
        ->and(makeAdminRole('technical')->hasPermission(Permission::RESIDENTS_CREATE))->toBeFalse()
        ->and(makeAdminRole('operator')->hasPermission(Permission::RESIDENTS_CREATE))->toBeTrue()
        ->and(makeAdminRole('operator')->hasPermission(Permission::DEVICES_CREATE))->toBeFalse()
        ->and(makeAdminRole('finance')->hasPermission(Permission::REFUNDS_CREATE))->toBeTrue()
        ->and(makeAdminRole('finance')->hasPermission(Permission::DEVICES_VIEW))->toBeFalse()
        ->and(makeAdminRole('support')->hasPermission(Permission::DEVICES_VIEW))->toBeTrue()
        ->and(makeAdminRole('support')->hasPermission(Permission::RESIDENTS_DELETE))->toBeFalse()
        ->and(makeAdminRole('complex_manager')->hasPermission(Permission::WHITELIST_MANAGE))->toBeTrue()
        ->and(makeAdminRole('complex_manager')->hasPermission(Permission::DEVICES_CREATE))->toBeFalse();
});

it('matches the DB-driven path to the matrix once seeded', function () {
    seedRbac();
    $finance = makeAdminRole('finance');

    expect($finance->hasPermission(Permission::REFUNDS_CREATE))->toBeTrue()
        ->and($finance->hasPermission(Permission::DEVICES_VIEW))->toBeFalse();
});

// ---- endpoint enforcement (403) ----

it('forbids finance from creating a device (devices.create)', function () {
    $model = makeDeviceModel();
    $this->actingAs(makeAdminRole('finance'), 'admin')->postJson('/admin/v1/devices', [
        'serial' => 'RBAC-1', 'device_model_id' => $model->id, 'sim_phone' => '+994701700001', 'driver_type' => 'traccar',
    ])->assertStatus(403);
});

it('forbids technical from listing orders (orders.view)', function () {
    $this->actingAs(makeAdminRole('technical'), 'admin')->getJson('/admin/v1/orders')->assertStatus(403);
});

it('forbids support from removing a resident (residents.delete)', function () {
    $owner = makeUser('+994507700010');
    $device = makeOwnedDevice($owner, 'RBAC-RM', '+994701700011');
    $member = makeUser('+994507700012');
    makeDeviceUser($member, $device, 'user');

    $this->actingAs(makeAdminRole('support'), 'admin')
        ->deleteJson("/admin/v1/devices/{$device->id}/users/{$member->id}")->assertStatus(403);
});

// ---- endpoint enforcement (allowed) ----

it('allows technical to create a device, finance to list orders, support to view devices, operator to add residents', function () {
    $model = makeDeviceModel();
    $this->actingAs(makeAdminRole('technical'), 'admin')->postJson('/admin/v1/devices', [
        'serial' => 'RBAC-OK', 'device_model_id' => $model->id, 'sim_phone' => '+994701700020', 'driver_type' => 'traccar',
    ])->assertStatus(201);

    $this->actingAs(makeAdminRole('finance'), 'admin')->getJson('/admin/v1/orders')->assertOk();
    $this->actingAs(makeAdminRole('support'), 'admin')->getJson('/admin/v1/devices')->assertOk();

    $device = makeOwnedDevice(makeUser('+994507700030'), 'RBAC-OP', '+994701700031');
    $this->actingAs(makeAdminRole('operator'), 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994507700032'])->assertStatus(201);
});

// ---- complex scoping ----

it('scopes complex_manager to its own complex devices', function () {
    $complexA = makeComplex('CX-A', 'A');
    $complexB = makeComplex('CX-B', 'B');
    $deviceA = makeUnassignedDevice('CX-DEV-A', '+994701700040'); $deviceA->update(['complex_id' => $complexA->id]);
    $deviceB = makeUnassignedDevice('CX-DEV-B', '+994701700041'); $deviceB->update(['complex_id' => $complexB->id]);

    $manager = makeAdminRole('complex_manager', (int) $complexA->id);

    $this->actingAs($manager, 'admin')->getJson('/admin/v1/devices')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.serial', 'CX-DEV-A');

    $this->actingAs($manager, 'admin')->getJson("/admin/v1/devices/{$deviceA->id}")->assertOk();
    $this->actingAs($manager, 'admin')->getJson("/admin/v1/devices/{$deviceB->id}")->assertStatus(404);
});

// ---- /me permissions ----

it('returns the resolved permissions on /me', function () {
    $this->actingAs(makeAdminRole('operator'), 'admin')->getJson('/admin/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('role', 'operator')
        ->assertJsonFragment(['permissions' => array_values(\App\Domain\Admin\Authorization\RolePermissionMatrix::forRole(\App\Domain\Admin\Enums\AdminRole::Operator))]);
});

// ---- admins management ----

it('lets a super admin create an admin and forbids non-super', function () {
    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/admins', [
        'email' => 'new-tech@salamhayetimiz.az', 'name' => 'New Tech', 'password' => 'create-12chars!', 'role' => 'technical',
    ])->assertStatus(201)->assertJsonPath('admin.role', 'technical')->assertJsonStructure(['totp_secret', 'recovery_codes']);

    expect(AdminUser::query()->where('email', 'new-tech@salamhayetimiz.az')->exists())->toBeTrue();

    $this->actingAs(makeAdminRole('operator'), 'admin')->postJson('/admin/v1/admins', [
        'email' => 'x@salamhayetimiz.az', 'name' => 'X', 'password' => 'create-12chars!', 'role' => 'technical',
    ])->assertStatus(403);
});

it('blocks deactivating the last active super admin', function () {
    $super = makeSuperAdmin();
    $target = makeSuperAdmin('other-super@salamhayetimiz.az');
    // two supers exist → deactivating one is allowed; then the last is blocked
    $this->actingAs($super, 'admin')->deleteJson("/admin/v1/admins/{$target->id}")->assertStatus(204);
    $this->actingAs($super, 'admin')->deleteJson("/admin/v1/admins/{$super->id}")->assertStatus(422);
});

// ---- impersonation ----

it('issues an impersonation token for the target carrying the impersonator claim', function () {
    $super = makeSuperAdmin();
    $target = makeAdminRole('finance', null, 'imp-target@salamhayetimiz.az');

    $res = $this->postJson("/admin/v1/admins/{$target->id}/impersonate", [], bearer(adminAccessToken($super)))
        ->assertOk()->assertJsonPath('admin.id', $target->id)->json();

    $claims = app(JwtService::class)->parseAdminClaims($res['access_token']);
    expect($claims['admin_id'])->toBe((int) $target->id)        // token now acts as the target
        ->and($claims['impersonator'])->toBe((int) $super->id); // and records who is impersonating
});

it('forbids a non-super admin from impersonating', function () {
    $target = makeAdminRole('finance', null, 'imp-t2@salamhayetimiz.az');
    $this->actingAs(makeAdminRole('operator'), 'admin')
        ->postJson("/admin/v1/admins/{$target->id}/impersonate")->assertStatus(403);
});

it('stop-impersonation returns a clean token for the original admin', function () {
    $super = makeSuperAdmin();
    $target = makeAdminRole('finance', null, 'imp-target3@salamhayetimiz.az');
    $impToken = app(JwtService::class)->issueAdminAccessToken((int) $target->id, 'finance', (int) $super->id)->token;

    $res = $this->postJson('/admin/v1/auth/stop-impersonation', [], bearer($impToken))->assertOk()->json();

    $claims = app(JwtService::class)->parseAdminClaims($res['access_token']);
    expect($claims['admin_id'])->toBe((int) $super->id)
        ->and($claims['impersonator'])->toBeNull();
});

it('rejects stop-impersonation when not impersonating', function () {
    $this->postJson('/admin/v1/auth/stop-impersonation', [], bearer(adminAccessToken(makeSuperAdmin())))
        ->assertStatus(422);
});

// ---- audit ----

it('records an audit entry for an administrative action', function () {
    $device = makeUnassignedDevice('AUD-1', '+994701700050');
    $this->actingAs(makeAdminRole('operator'), 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/assign", ['owner_phone' => '+994507700051'])->assertOk();

    expect(AuditLog::query()->where('action', 'device.assigned')->where('actor_kind', 'admin')->exists())->toBeTrue();
});
