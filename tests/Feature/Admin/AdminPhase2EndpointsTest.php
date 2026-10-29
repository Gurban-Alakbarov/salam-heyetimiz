<?php

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\DeviceComm\Models\DeviceDiagnostic;

/*
| Admin audit phase 2 (2026-10-29): device ping for support, global search (permission + komendant scope),
| audit-log filters, complex coordinates.
*/

beforeEach(fn () => seedRbac());

it('pings a device through Traccar and records an admin_ping diagnostic', function () {
    $device = makeActiveDevice('PING-1', '+994700710001');

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson("/admin/v1/devices/{$device->id}/ping")
        ->assertOk()
        ->assertJsonStructure(['reachable', 'found', 'status', 'online', 'last_online_at']);

    expect(DeviceDiagnostic::query()->where('device_id', $device->id)->where('source', 'admin_ping')->exists())->toBeTrue();
});

it('keeps a komendant ping inside their own complex', function () {
    $complex = makeComplex('CX-PING', 'Ping Complex');
    $other = makeActiveDevice('PING-2', '+994700710002'); // no complex
    $komendant = makeAdminRole('complex_manager', (int) $complex->id);
    \App\Domain\Admin\Models\UserPermission::query()->create([
        'admin_user_id' => $komendant->id,
        'permission_id' => (int) \App\Domain\Admin\Models\Permission::query()->where('key', 'devices.diagnostics.view')->value('id'),
        'effect' => 'grant',
    ]);

    $this->actingAs($komendant->fresh(), 'admin')->postJson("/admin/v1/devices/{$other->id}/ping")->assertNotFound();
});

it('searches devices, people, complexes and orders in one call', function () {
    $device = makeActiveDevice('SRCH-IMEI-777', '+994700720001');
    $user = makeUser('+994507720002');
    $user->forceFill(['full_name' => 'Srch Tester', 'email' => 'srch@example.com'])->save();
    makeComplex('SRCH-CX', 'Srch Residence');

    $res = $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/search?q=srch')->assertOk();

    expect(collect($res->json('devices'))->pluck('id'))->toContain($device->id)
        ->and(collect($res->json('people'))->pluck('id'))->toContain($user->id)
        ->and(collect($res->json('complexes'))->pluck('code'))->toContain('SRCH-CX');
});

it('shows a komendant only their own complex in search, and no orders', function () {
    $mine = makeComplex('KSR-1', 'Ksr Mine');
    makeComplex('KSR-2', 'Ksr Other');
    $komendant = makeAdminRole('complex_manager', (int) $mine->id);

    $res = $this->actingAs($komendant, 'admin')->getJson('/admin/v1/search?q=ksr')->assertOk();

    expect(collect($res->json('complexes'))->pluck('code')->all())->toBe(['KSR-1'])
        ->and($res->json('orders'))->toBe([]);
});

it('filters the audit log by area, object and free text', function () {
    $admin = makeSuperAdmin();
    $this->actingAs($admin, 'admin');
    app(AuditLogger::class)->record('device.image_uploaded', ['device_id' => 41], 'App\\Domain\\Devices\\Models\\Device', 41);
    app(AuditLogger::class)->record('complex.updated', ['code' => 'ZZZ-9'], 'App\\Domain\\Admin\\Models\\Complex', 9);

    $byPrefix = $this->getJson('/admin/v1/audit?action_prefix=device.')->assertOk()->json('data');
    $byObject = $this->getJson('/admin/v1/audit?auditable_type=Complex&auditable_id=9')->assertOk()->json('data');
    $byText = $this->getJson('/admin/v1/audit?q=ZZZ-9')->assertOk()->json('data');

    expect(collect($byPrefix)->pluck('action')->unique()->all())->toBe(['device.image_uploaded'])
        ->and(collect($byObject)->pluck('auditable_id')->all())->toBe([9])
        ->and(collect($byText)->pluck('action')->all())->toBe(['complex.updated']);
});

it('stores complex coordinates on create and update', function () {
    $admin = makeSuperAdmin();
    $id = $this->actingAs($admin, 'admin')->postJson('/admin/v1/complexes', [
        'name' => 'Geo Complex', 'code' => 'GEO-1', 'latitude' => 40.4093, 'longitude' => 49.8671,
    ])->assertCreated()->json('id');

    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/complexes/{$id}", ['latitude' => 40.5, 'longitude' => 49.9, 'name' => 'Geo 2'])
        ->assertOk()->assertJsonPath('name', 'Geo 2');

    $c = \App\Domain\Admin\Models\Complex::query()->findOrFail($id);
    expect((float) $c->latitude)->toBe(40.5)->and((float) $c->longitude)->toBe(49.9);
});

it('finds event-written audit rows by the object id in their payload', function () {
    $this->actingAs(makeSuperAdmin(), 'admin');
    // domain-event rows carry the object only in the payload (no auditable_type)
    app(AuditLogger::class)->record('relay_open_requested', ['device_id' => 30]);
    app(AuditLogger::class)->record('relay_open_requested', ['device_id' => 31]);
    app(AuditLogger::class)->record('device.image_uploaded', ['device_id' => 30], 'App\Domain\Devices\Models\Device', 30);

    $rows = $this->getJson('/admin/v1/audit?auditable_type=Device&auditable_id=30')->assertOk()->json('data');

    expect(collect($rows)->pluck('action')->sort()->values()->all())->toBe(['device.image_uploaded', 'relay_open_requested']);
});
