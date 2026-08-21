<?php

/*
| GET /v1/devices — listMyDevices (owned + member, per-caller role/can_open/suspension, filters).
*/

it('lists the caller’s owned and member devices with per-caller role', function () {
    $me = makeUser('+994506000001');
    $ownerOfOther = makeUser('+994506000002');

    $owned = makeOwnedDevice($me, 'SER-L1', '+994700006001');

    // a device I'm a (non-owner) member of
    $shared = makeOwnedDevice($ownerOfOther, 'SER-L2', '+994700006002');
    makeDeviceUser($me, $shared, 'user');

    // a device I have nothing to do with
    makeOwnedDevice(makeUser('+994506000003'), 'SER-L3', '+994700006003');

    $response = $this->actingAs($me, 'user')->getJson('/v1/devices');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'label', 'status', 'role', 'can_open', 'suspension_reason']], 'page']);

    $roles = collect($response->json('data'))->pluck('role', 'id');
    expect($roles[$owned->id])->toBe('owner')
        ->and($roles[$shared->id])->toBe('user');
});

it('filters to owned devices only', function () {
    $me = makeUser('+994506000010');
    $owned = makeOwnedDevice($me, 'SER-L4', '+994700006010');
    $shared = makeOwnedDevice(makeUser('+994506000011'), 'SER-L5', '+994700006011');
    makeDeviceUser($me, $shared, 'user');

    $this->actingAs($me, 'user')->getJson('/v1/devices?filter=owned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $owned->id);
});

it('filters to member devices only', function () {
    $me = makeUser('+994506000020');
    makeOwnedDevice($me, 'SER-L6', '+994700006020');
    $shared = makeOwnedDevice(makeUser('+994506000021'), 'SER-L7', '+994700006021');
    makeDeviceUser($me, $shared, 'user');

    $this->actingAs($me, 'user')->getJson('/v1/devices?filter=member')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $shared->id);
});

it('reports can_open true for an owner with an active subscription', function () {
    $me = makeUser('+994506000030');
    $device = makeOwnedDevice($me, 'SER-L8', '+994700006030');
    $du = \App\Domain\Roster\Models\DeviceUser::query()
        ->where('device_id', $device->id)->where('user_id', $me->id)->firstOrFail();
    makeSubscription($du); // active sub on the owner's roster row

    $this->actingAs($me, 'user')->getJson('/v1/devices?filter=owned')
        ->assertOk()
        ->assertJsonPath('data.0.can_open', true)
        ->assertJsonPath('data.0.suspension_reason', 'none');
});

it('requires authentication', function () {
    $this->getJson('/v1/devices')->assertStatus(401);
});
