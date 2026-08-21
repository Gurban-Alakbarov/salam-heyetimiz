<?php

use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use App\Domain\DeviceComm\Services\TraccarIngestionService;

/*
| GET /admin/v1/devices/{id}/diagnostics — adminDeviceDiagnostics (history, newest first).
*/

it('returns the diagnostics history for an admin', function () {
    $admin = makeSuperAdmin();
    [$device] = openableWithRoster('+994551400001', 'DIAG-1', '+994700114001');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-DIAG-1');
    app(TraccarIngestionService::class)->ingest([
        'device' => ['uniqueId' => 'IMEI-DIAG-1', 'status' => 'online'],
        'position' => ['deviceTime' => '2026-06-14T08:00:00Z', 'attributes' => ['rssi' => 20, 'battery' => 77]],
    ]);

    $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}/diagnostics")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.online', true)
        ->assertJsonPath('data.0.signal_strength', 20)
        ->assertJsonStructure([
            'data' => [['id', 'device_id', 'source', 'online', 'signal_strength', 'battery_level', 'firmware_version', 'reported_at']],
            'page' => ['next_cursor', 'has_more', 'limit'],
        ]);
});

it('requires an admin token for diagnostics', function () {
    [$device] = openableWithRoster('+994551400002', 'DIAG-2', '+994700114002');

    $this->getJson("/admin/v1/devices/{$device->id}/diagnostics")->assertStatus(401);
});
