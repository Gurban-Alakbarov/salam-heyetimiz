<?php

use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\UserPermission;

/*
| Dynamic Role + Permission engine: effective = (role ∪ grants) \ revokes. Plus the Access Control API
| (grant/revoke/reset/roles/permissions) and Complex Management CRUD + complex_manager scoping.
*/

function grantTo(int $adminId, string $key, string $effect = 'grant'): void
{
    UserPermission::query()->create([
        'admin_user_id' => $adminId,
        'permission_id' => (int) Permission::query()->where('key', $key)->value('id'),
        'effect' => $effect,
    ]);
}

it('computes effective permissions as role + grants - revokes (overrides beat the role)', function () {
    seedRbac();

    $tech1 = makeAdminRole('technical');
    $tech2 = makeAdminRole('technical');
    grantTo($tech1->id, 'orders.view');
    grantTo($tech1->id, 'refunds.view');

    expect($tech1->fresh()->hasPermission('orders.view'))->toBeTrue()   // gained finance access
        ->and($tech1->fresh()->hasPermission('refunds.view'))->toBeTrue()
        ->and($tech2->fresh()->hasPermission('orders.view'))->toBeFalse(); // same role, still no access

    $fin1 = makeAdminRole('finance');
    $fin2 = makeAdminRole('finance');
    grantTo($fin1->id, 'refunds.create', 'revoke');

    expect($fin1->fresh()->hasPermission('refunds.create'))->toBeFalse() // revoked, still Finance
        ->and($fin1->fresh()->hasPermission('orders.view'))->toBeTrue()  // keeps the rest
        ->and($fin2->fresh()->hasPermission('refunds.create'))->toBeTrue();
});

it('keeps super_admin all-powerful regardless of overrides', function () {
    seedRbac();
    $super = makeSuperAdmin();
    grantTo($super->id, 'orders.view', 'revoke');

    expect($super->fresh()->hasPermission('orders.view'))->toBeTrue()
        ->and($super->fresh()->grantedKeys())->toBe([])
        ->and($super->fresh()->revokedKeys())->toBe([]);
});

it('grants/revokes/resets via the access endpoints and updates live authorization', function () {
    seedRbac();
    $super = makeSuperAdmin();
    $tech = makeAdminRole('technical');

    $this->actingAs($tech, 'admin')->getJson('/admin/v1/orders')->assertStatus(403); // before

    $res = $this->actingAs($super, 'admin')
        ->postJson("/admin/v1/admins/{$tech->id}/permissions/grant", ['permission' => 'orders.view'])
        ->assertOk()->json();
    expect($res['additional'])->toContain('orders.view')
        ->and($res['effective'])->toContain('orders.view');

    // fresh() each time: in prod every request re-resolves the admin (no stale per-instance cache)
    $this->actingAs($tech->fresh(), 'admin')->getJson('/admin/v1/orders')->assertOk(); // immediately effective

    // revoke a default
    $this->actingAs($super, 'admin')
        ->postJson("/admin/v1/admins/{$tech->id}/permissions/revoke", ['permission' => 'devices.create'])
        ->assertOk()->assertJsonPath('revoked', ['devices.create']);
    $model = makeDeviceModel();
    $this->actingAs($tech->fresh(), 'admin')->postJson('/admin/v1/devices', [
        'serial' => 'DYN-1', 'device_model_id' => $model->id, 'sim_phone' => '+994701990001', 'driver_type' => 'traccar',
    ])->assertStatus(403); // create now revoked

    // reset → back to role defaults
    $reset = $this->actingAs($super, 'admin')->postJson("/admin/v1/admins/{$tech->id}/permissions/reset")->assertOk()->json();
    expect($reset['additional'])->toBe([])->and($reset['revoked'])->toBe([]);
    $this->actingAs($tech->fresh(), 'admin')->getJson('/admin/v1/orders')->assertStatus(403); // grant gone
});

it('restricts the access-control API to access.manage (super only)', function () {
    seedRbac();
    $tech = makeAdminRole('technical');
    $this->actingAs(makeAdminRole('operator'), 'admin')->getJson('/admin/v1/access/permissions')->assertStatus(403);
    $this->actingAs(makeAdminRole('operator'), 'admin')
        ->postJson("/admin/v1/admins/{$tech->id}/permissions/grant", ['permission' => 'orders.view'])->assertStatus(403);
    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/access/roles')->assertOk()->assertJsonStructure(['data' => [['role', 'default_permissions']]]);
    $this->actingAs(makeSuperAdmin('s9@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/access/permissions')->assertOk();
});

it('manages complexes and scopes a complex_manager to its own complex', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $cx = $this->actingAs($super, 'admin')->postJson('/admin/v1/complexes', ['name' => 'Test CX', 'code' => 'TCX-1'])
        ->assertStatus(201)->assertJsonPath('code', 'TCX-1')->json();
    $other = $this->actingAs($super, 'admin')->postJson('/admin/v1/complexes', ['name' => 'Other CX', 'code' => 'TCX-2'])
        ->assertStatus(201)->json();

    $this->actingAs($super, 'admin')->getJson("/admin/v1/complexes/{$cx['id']}")
        ->assertOk()->assertJsonStructure(['id', 'code', 'stats' => ['devices', 'residents', 'managers'], 'managers', 'devices']);

    $mgr = makeAdminRole('complex_manager');
    $this->actingAs($super, 'admin')->postJson("/admin/v1/complexes/{$cx['id']}/managers", ['admin_id' => $mgr->id])->assertOk();
    expect((int) $mgr->fresh()->complex_id)->toBe((int) $cx['id']);

    // manager sees only its complex; another complex → 404
    $this->actingAs($mgr->fresh(), 'admin')->getJson('/admin/v1/complexes')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($mgr->fresh(), 'admin')->getJson("/admin/v1/complexes/{$other['id']}")->assertStatus(404);
    $this->actingAs($mgr->fresh(), 'admin')->getJson("/admin/v1/complexes/{$cx['id']}")->assertOk();

    // operator cannot manage complexes
    $this->actingAs(makeAdminRole('operator'), 'admin')->postJson('/admin/v1/complexes', ['name' => 'X', 'code' => 'X-9'])->assertStatus(403);
});
