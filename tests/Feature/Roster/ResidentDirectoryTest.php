<?php

use App\Domain\Roster\Models\DeviceUser;

/*
| GET /admin/v1/residents — every resident with their complex, device status/connectivity and subscription.
| residents.view; complex_manager scoped to its own complex.
*/

it('lists residents with complex, device status and subscription', function () {
    seedRbac();
    $cx = makeComplex('RES-CX', 'Res Complex');
    $owner = makeUser('+994701888001');
    $device = makeOwnedDevice($owner, 'RES-D1', '+994701888002');
    $device->update(['complex_id' => $cx->id, 'last_online_at' => now()]);
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    makeSubscription($du); // active main subscription

    $res = $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/residents')->assertOk()->json();

    expect($res['data'])->toHaveCount(1);
    $row = $res['data'][0];
    expect($row['complex']['name'])->toBe('Res Complex')
        ->and($row['device']['serial'])->toBe('RES-D1')
        ->and($row['device']['online'])->toBeTrue()
        ->and($row['subscription']['status'])->toBe('active')
        ->and($row['role'])->toBe('owner');
});

it('forbids roles without residents.view', function () {
    seedRbac();
    $this->actingAs(makeAdminRole('finance'), 'admin')->getJson('/admin/v1/residents')->assertStatus(403);
    $this->actingAs(makeAdminRole('technical'), 'admin')->getJson('/admin/v1/residents')->assertStatus(403);
    $this->actingAs(makeAdminRole('support'), 'admin')->getJson('/admin/v1/residents')->assertOk(); // support has residents.view
});

it('scopes residents to the complex_manager own complex', function () {
    seedRbac();
    $cx1 = makeComplex('RC1', 'Complex 1');
    $cx2 = makeComplex('RC2', 'Complex 2');
    $d1 = makeOwnedDevice(makeUser('+994701888020'), 'RC-D1', '+994701888021');
    $d1->update(['complex_id' => $cx1->id]);
    $d2 = makeOwnedDevice(makeUser('+994701888030'), 'RC-D2', '+994701888031');
    $d2->update(['complex_id' => $cx2->id]);

    $mgr = makeAdminRole('complex_manager', (int) $cx1->id);
    $this->actingAs($mgr, 'admin')->getJson('/admin/v1/residents')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.complex.name', 'Complex 1');
});
