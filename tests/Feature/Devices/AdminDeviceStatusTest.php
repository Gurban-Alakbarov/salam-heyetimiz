<?php

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceDecommissioned;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Support\Facades\Event;

/*
| Admin device status transitions: disable / enable / decommission (R-DOM-05/06).
*/

it('disables a device as super admin', function () {
    $admin = makeSuperAdmin();
    $device = makeOwnedDevice(makeUser('+994506400001'), 'DIS-1', '+994701400001');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/disable", [
        'reason' => 'tamper suspected',
    ])->assertOk()->assertJsonPath('status', 'disabled');

    expect($device->fresh()->status)->toBe(DeviceStatus::Disabled);
});

it('re-enables a disabled device back to its assigned state', function () {
    $admin = makeSuperAdmin();
    $device = makeOwnedDevice(makeUser('+994506400002'), 'ENA-1', '+994701400002');
    $device->update(['status' => DeviceStatus::Disabled->value]);

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/enable")
        ->assertOk()
        ->assertJsonPath('status', 'active');

    expect($device->fresh()->status)->toBe(DeviceStatus::Active);
});

it('decommissions a device, soft-deletes it, and revokes the roster', function () {
    Event::fake([DeviceDecommissioned::class]);
    $admin = makeSuperAdmin();
    $owner = makeUser('+994506400003');
    $device = makeOwnedDevice($owner, 'DEC-1', '+994701400003');

    $this->actingAs($admin, 'admin')->deleteJson("/admin/v1/devices/{$device->id}", [
        'reason' => 'returned to depot',
    ])->assertStatus(204);

    $trashed = Device::withTrashed()->findOrFail($device->id);
    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->status)->toBe(DeviceStatus::Decommissioned)
        ->and(DeviceUser::query()->where('device_id', $device->id)->where('status', 'active')->count())->toBe(0);

    Event::assertDispatched(DeviceDecommissioned::class);
});

it('forbids a technical admin from disabling (super_admin only)', function () {
    $tech = makeTechnicalAdmin();
    $device = makeActiveDevice('DIS-2', '+994701400004');

    $this->actingAs($tech, 'admin')->postJson("/admin/v1/devices/{$device->id}/disable", [
        'reason' => 'nope',
    ])->assertStatus(403);
});
