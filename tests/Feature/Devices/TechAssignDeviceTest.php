<?php

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\DeviceUserHistory;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Event;

/*
| POST /v1/technical/devices/{id}/assign — techAssignDevice (bind owner, activate, seed roster).
*/

it('assigns an owner, activates the device, and seeds the owner roster row', function () {
    Event::fake([DeviceAssigned::class]);
    $tech = makeAdminRole('operator'); // assignment is an operator capability (devices.assign), not technical
    $device = makeUnassignedDevice('SN-AS-1', '+994701120001');

    $response = $this->actingAs($tech, 'admin')->postJson("/v1/technical/devices/{$device->id}/assign", [
        'owner_phone' => '+994507000001',
        'location_label' => 'Yard gate',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('location_label', 'Yard gate');

    $owner = User::query()->where('phone', '+994507000001')->firstOrFail();
    $device->refresh();
    expect($device->status)->toBe(DeviceStatus::Active)
        ->and((int) $device->owner_user_id)->toBe($owner->id);

    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    expect($du->role->value)->toBe('owner')
        ->and($du->status->value)->toBe('active')
        ->and(DeviceUserHistory::query()->where('device_id', $device->id)->where('event', 'added')->count())->toBe(1);

    Event::assertDispatched(DeviceAssigned::class);
});

it('rejects assigning an already-owned device with 409', function () {
    $tech = makeAdminRole('operator'); // assignment is an operator capability (devices.assign), not technical
    $device = makeOwnedDevice(makeUser('+994507000010'), 'SN-AS-2', '+994701120002');

    $this->actingAs($tech, 'admin')->postJson("/v1/technical/devices/{$device->id}/assign", [
        'owner_phone' => '+994507000011',
    ])->assertStatus(409)->assertJsonPath('error.code', 'device_already_assigned');
});
