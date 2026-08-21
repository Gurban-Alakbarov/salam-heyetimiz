<?php

use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\Devices\Events\DeviceDecommissioned;
use App\Domain\Devices\Events\DeviceTransferred;
use App\Domain\DeviceComm\Models\WhitelistChange;

/*
| Whitelist outbox (R-GSM-07): device ownership events enqueue pending changes; admin resync enqueues
| a clear + add-all at priority 10; the queue is inspectable. No device is touched (drain is GSM).
*/

it('enqueues an add when a device is assigned an owner', function () {
    $owner = makeUser('+994509500001');
    $device = makeOwnedDevice($owner, 'SER-W1', '+994700095001');

    DeviceAssigned::dispatch($device, $owner->id, 'technical');

    $change = WhitelistChange::query()->where('device_id', $device->id)->firstOrFail();
    expect($change->action->value)->toBe('add')
        ->and($change->phone)->toBe($owner->phone)
        ->and($change->status->value)->toBe('pending')
        ->and($change->seq)->toBe($change->id); // monotonic drain key
});

it('enqueues a remove + add on transfer', function () {
    $oldOwner = makeUser('+994509500002');
    $newOwner = makeUser('+994509500003');
    $device = makeOwnedDevice($oldOwner, 'SER-W2', '+994700095002');

    DeviceTransferred::dispatch($device, $oldOwner->id, $newOwner->id, true, 'sold');

    expect(WhitelistChange::query()->where('device_id', $device->id)->where('action', 'remove')->where('phone', $oldOwner->phone)->exists())->toBeTrue()
        ->and(WhitelistChange::query()->where('device_id', $device->id)->where('action', 'add')->where('phone', $newOwner->phone)->exists())->toBeTrue();
});

it('enqueues a clear on decommission', function () {
    $owner = makeUser('+994509500004');
    $device = makeOwnedDevice($owner, 'SER-W3', '+994700095003');

    DeviceDecommissioned::dispatch($device->id, 'retired');

    expect(WhitelistChange::query()->where('device_id', $device->id)->where('action', 'clear')->exists())->toBeTrue();
});

it('resyncs the whitelist as an admin (clear + add-all at priority 10)', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509500005');
    $device = makeOwnedDevice($owner, 'SER-W4', '+994700095004');

    $this->actingAs($admin, 'admin')->postJson("/admin/v1/devices/{$device->id}/whitelist/resync")
        ->assertStatus(202);

    $changes = WhitelistChange::query()->where('device_id', $device->id)->get();
    expect($changes)->toHaveCount(2)
        ->and($changes->where('action', 'clear')->count())->toBe(1)
        ->and($changes->where('action', 'add')->where('phone', $owner->phone)->count())->toBe(1)
        ->and($changes->every(fn ($c) => $c->priority === 10))->toBeTrue();
});

it('lists the whitelist queue for an admin', function () {
    $admin = makeSuperAdmin();
    $owner = makeUser('+994509500006');
    $device = makeOwnedDevice($owner, 'SER-W5', '+994700095005');
    DeviceAssigned::dispatch($device, $owner->id, 'purchase');

    $this->actingAs($admin, 'admin')->getJson("/admin/v1/devices/{$device->id}/whitelist-queue?status=pending")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'device_id', 'action', 'phone', 'status', 'attempt_count']], 'page'])
        ->assertJsonPath('data.0.action', 'add');
});

it('requires an admin token for the whitelist queue', function () {
    $this->getJson('/admin/v1/devices/1/whitelist-queue')->assertStatus(401);
});
