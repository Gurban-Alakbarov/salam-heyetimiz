<?php

use App\Domain\Admin\Models\UserPermission;
use App\Domain\Admin\Models\Permission;

/*
| 2026-10-29 owner decisions:
|  - the technician finishes an install alone: sees the complexes, binds the device, assigns the owner;
|  - a komendant (complex_manager) only ever sees their own complex — permissions served by endpoints that are not
|    complex-scoped can neither be granted to them nor take effect if they somehow exist.
*/

beforeEach(fn () => seedRbac());

it('lets the technician bind a device to a complex and assign its owner', function () {
    $tech = makeTechnicalAdmin();
    $complex = makeComplex('CX-TECH', 'Tech Complex');
    $device = makeUnassignedDevice('TECH-BIND-1', '+994700610001');

    $this->actingAs($tech, 'admin')->getJson('/admin/v1/complexes')->assertOk();
    $this->actingAs($tech, 'admin')->postJson("/admin/v1/complexes/{$complex->id}/devices/{$device->id}")->assertOk();
    expect((int) $device->refresh()->complex_id)->toBe((int) $complex->id);

    $this->actingAs($tech, 'admin')->postJson("/admin/v1/devices/{$device->id}/assign", [
        'owner_phone' => '+994505610002',
    ])->assertOk();

    $this->actingAs($tech, 'admin')->deleteJson("/admin/v1/complexes/{$complex->id}/devices/{$device->id}")->assertOk();
});

it('still keeps complex creation and komendant assignment away from the technician', function () {
    $tech = makeTechnicalAdmin();

    $this->actingAs($tech, 'admin')->postJson('/admin/v1/complexes', ['name' => 'X', 'code' => 'X-1'])->assertStatus(403);
});

it('refuses to grant a komendant a permission that would show other complexes', function () {
    $complex = makeComplex('CX-KOM', 'Kom Complex');
    $komendant = makeAdminRole('complex_manager', (int) $complex->id);

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->postJson("/admin/v1/admins/{$komendant->id}/permissions/grant", ['permission' => 'orders.view'])
        ->assertStatus(422);

    $this->actingAs(makeSuperAdmin('s2@salamhayetimiz.az'), 'admin')
        ->postJson("/admin/v1/admins/{$komendant->id}/permissions/grant", ['permission' => 'commands.view'])
        ->assertOk();
});

it('refuses a complex_manager role template with an unscoped permission', function () {
    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/access/roles/complex_manager', ['permissions' => ['residents.view', 'refunds.view']])
        ->assertStatus(422);
});

it('ignores an unscoped grant that already exists for a komendant', function () {
    $complex = makeComplex('CX-KOM2', 'Kom Complex 2');
    $komendant = makeAdminRole('complex_manager', (int) $complex->id);
    UserPermission::query()->create([
        'admin_user_id' => $komendant->id,
        'permission_id' => (int) Permission::query()->where('key', 'orders.view')->value('id'),
        'effect' => 'grant',
    ]);

    expect($komendant->fresh()->hasPermission('orders.view'))->toBeFalse();
    $this->actingAs($komendant->fresh(), 'admin')->getJson('/admin/v1/orders')->assertStatus(403);
});

it('lists device models, SIM operators and regions for the device form', function () {
    makeDeviceModel('LookupVendor', 'LK-1');

    $this->actingAs(makeTechnicalAdmin(), 'admin')->getJson('/admin/v1/devices/lookups')
        ->assertOk()
        ->assertJsonStructure(['device_models' => [['id', 'vendor', 'model_code', 'default_driver_type']], 'sim_operators', 'regions']);
});
