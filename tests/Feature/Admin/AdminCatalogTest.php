<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Catalog\Models\DeviceModel;

/*
| Admin audit phase 3 (2026-10-29): device models, regions and SIM operators managed in the panel (catalog.manage,
| super admin by default). No deletes — is_active hides a row from the device form. Model changes are audited old→new.
*/

beforeEach(fn () => seedRbac());

it('creates and edits a device model, auditing old → new', function () {
    $admin = makeSuperAdmin();
    $id = $this->actingAs($admin, 'admin')->postJson('/admin/v1/catalog/device-models', [
        'vendor' => 'Jimi', 'model_code' => 'VL999', 'default_driver_type' => 'traccar',
        'open_command' => 'RELAY,1#', 'close_command' => 'RELAY,0#', 'open_pulse_ms' => 1500, 'whitelist_capacity' => 200,
    ])->assertCreated()->assertJsonPath('open_command', 'RELAY,1#')->json('id');

    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/catalog/device-models/{$id}", ['open_pulse_ms' => 2000, 'is_active' => false])
        ->assertOk()->assertJsonPath('open_pulse_ms', 2000)->assertJsonPath('is_active', false);

    $log = AuditLog::query()->where('action', 'catalog.device_model_updated')->latest('id')->firstOrFail();
    expect(collect($log->payload['changes'])->firstWhere('key', 'open_pulse_ms'))->toMatchArray(['old' => 1500, 'new' => 2000]);

    // an inactive model disappears from the device form's choices
    $lookups = $this->actingAs($admin, 'admin')->getJson('/admin/v1/devices/lookups')->json('device_models');
    expect(collect($lookups)->pluck('id'))->not->toContain($id);
});

it('refuses a duplicate vendor + model code', function () {
    makeDeviceModel('DupVendor', 'DUP-1');

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/catalog/device-models', ['vendor' => 'DupVendor', 'model_code' => 'DUP-1'])
        ->assertStatus(422);
});

it('manages regions and SIM operators', function () {
    $admin = makeSuperAdmin();
    $r = $this->actingAs($admin, 'admin')->postJson('/admin/v1/catalog/regions', ['code' => 'RG-X', 'name' => 'Region X'])->assertCreated()->json('id');
    $o = $this->actingAs($admin, 'admin')->postJson('/admin/v1/catalog/sim-operators', ['code' => 'OP-X', 'name' => 'Operator X'])->assertCreated()->json('id');
    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/catalog/regions/{$r}", ['name' => 'Region Y'])->assertOk();
    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/catalog/sim-operators/{$o}", ['is_active' => false])->assertOk();

    $catalog = $this->actingAs($admin, 'admin')->getJson('/admin/v1/catalog')->assertOk();
    expect(collect($catalog->json('regions'))->firstWhere('id', $r)['name'])->toBe('Region Y')
        ->and(collect($catalog->json('sim_operators'))->firstWhere('id', $o)['is_active'])->toBeFalse();
});

it('keeps the catalog away from roles without catalog.manage', function () {
    $this->actingAs(makeTechnicalAdmin(), 'admin')->getJson('/admin/v1/catalog')->assertForbidden();
    $this->actingAs(makeTechnicalAdmin('t2@salamhayetimiz.az'), 'admin')
        ->postJson('/admin/v1/catalog/device-models', ['vendor' => 'X', 'model_code' => 'Y'])->assertForbidden();
});
