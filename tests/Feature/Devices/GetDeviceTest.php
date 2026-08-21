<?php

use App\Domain\Roster\Models\DeviceUser;

/*
| GET /v1/devices/{deviceId} — getDevice (DeviceDetail; owner-only/member, 404 otherwise).
*/

it('returns device detail with model and subscription for the owner', function () {
    $me = makeUser('+994506100001');
    $device = makeOwnedDevice($me, 'SER-G1', '+994700061001');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $me->id)->firstOrFail();
    makeSubscription($du);

    $response = $this->actingAs($me, 'user')->getJson("/v1/devices/{$device->id}");

    $response->assertOk()
        ->assertJsonPath('id', $device->id)
        ->assertJsonPath('role', 'owner')
        ->assertJsonPath('can_open', true)
        ->assertJsonPath('device_model.vendor', 'TestVendor')
        ->assertJsonPath('subscription.status', 'active')
        ->assertJsonStructure(['id', 'label', 'status', 'role', 'can_open', 'device_model', 'subscription', 'cooldown_seconds_remaining']);
});

it('hides a device the caller is not on behind a 404', function () {
    $me = makeUser('+994506100002');
    $other = makeUser('+994506100003');
    $device = makeOwnedDevice($other, 'SER-G2', '+994700061002');

    $this->actingAs($me, 'user')->getJson("/v1/devices/{$device->id}")->assertStatus(404);
});

it('returns 404 for a missing device', function () {
    $me = makeUser('+994506100004');

    $this->actingAs($me, 'user')->getJson('/v1/devices/999999')->assertStatus(404);
});
