<?php

use App\Domain\Devices\Models\Device;

/*
| GEOFENCE-1 (D5) — configuring a device geofence. The rule (enabling needs a positive radius AND
| device coordinates) lives in SetDeviceGeofence and is shared by BOTH the admin PATCH
| (/admin/v1/devices/{id}) and the owner PATCH (/v1/devices/{id}/geofence). Owners can only toggle the
| geofence + radius — never coordinates, ownership, or another user's device.
*/

/** A device that already carries coordinates (admin-set), ready for geofence enabling. */
function deviceWithCoords(string $serial, string $sim): Device
{
    $device = makeActiveDevice($serial, $sim);
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);

    return $device->refresh();
}

/*
|--------------------------------------------------------------------------
| Admin — PATCH /admin/v1/devices/{id}
|--------------------------------------------------------------------------
*/

it('lets an admin enable a geofence when the device has coordinates', function () {
    $admin = makeSuperAdmin();
    $device = deviceWithCoords('GF-ADM-1', '+994701400001');

    $this->actingAs($admin, 'admin')
        ->patchJson("/admin/v1/devices/{$device->id}", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 200,
        ])
        ->assertOk()
        ->assertJsonPath('geofence_enabled', true)
        ->assertJsonPath('geofence_radius_m', 200);

    $device->refresh();
    expect($device->geofence_enabled)->toBeTrue()
        ->and($device->geofence_radius_m)->toBe(200);
});

it('rejects an admin enabling a geofence without a radius', function () {
    $admin = makeSuperAdmin();
    $device = deviceWithCoords('GF-ADM-2', '+994701400002');

    $this->actingAs($admin, 'admin')
        ->patchJson("/admin/v1/devices/{$device->id}", ['geofence_enabled' => true])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'geofence_radius_required');

    expect($device->refresh()->geofence_enabled)->toBeFalse();
});

it('rejects an admin enabling a geofence on a device without coordinates', function () {
    $admin = makeSuperAdmin();
    $device = makeActiveDevice('GF-ADM-3', '+994701400003'); // no coordinates

    $this->actingAs($admin, 'admin')
        ->patchJson("/admin/v1/devices/{$device->id}", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 200,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'geofence_device_location_missing');
});

it('sets coordinates and the geofence in a single admin PATCH', function () {
    $admin = makeSuperAdmin();
    $device = makeActiveDevice('GF-ADM-4', '+994701400004'); // no coordinates yet

    // The coordinate update is applied first, so the geofence step sees them and enables successfully.
    $this->actingAs($admin, 'admin')
        ->patchJson("/admin/v1/devices/{$device->id}", [
            'latitude' => 40.0,
            'longitude' => 49.0,
            'geofence_enabled' => true,
            'geofence_radius_m' => 250,
        ])
        ->assertOk()
        ->assertJsonPath('geofence_enabled', true)
        ->assertJsonPath('geofence_radius_m', 250);
});

it('lets an admin disable a geofence and clears the radius', function () {
    $admin = makeSuperAdmin();
    $device = deviceWithCoords('GF-ADM-5', '+994701400005');
    $device->update(['geofence_enabled' => true, 'geofence_radius_m' => 200]);

    $this->actingAs($admin, 'admin')
        ->patchJson("/admin/v1/devices/{$device->id}", ['geofence_enabled' => false])
        ->assertOk()
        ->assertJsonPath('geofence_enabled', false)
        ->assertJsonPath('geofence_radius_m', null);

    $device->refresh();
    expect($device->geofence_enabled)->toBeFalse()
        ->and($device->geofence_radius_m)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Owner — PATCH /v1/devices/{id}/geofence
|--------------------------------------------------------------------------
*/

it('lets the owner enable a geofence on their own device', function () {
    $owner = makeUser('+994522000001');
    $device = makeOwnedDevice($owner, 'GF-OWN-1', '+994701410001');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);

    $this->actingAs($owner, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 150,
        ])
        ->assertOk()
        ->assertJsonPath('id', $device->id)
        ->assertJsonPath('geofence_enabled', true)
        ->assertJsonPath('geofence_radius_m', 150);

    $device->refresh();
    expect($device->geofence_enabled)->toBeTrue()
        ->and($device->geofence_radius_m)->toBe(150);
});

it('rejects the owner enabling a geofence without a radius', function () {
    $owner = makeUser('+994522000002');
    $device = makeOwnedDevice($owner, 'GF-OWN-2', '+994701410002');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);

    $this->actingAs($owner, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", ['geofence_enabled' => true])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'geofence_radius_required');
});

it('rejects the owner enabling a geofence on a device without coordinates', function () {
    $owner = makeUser('+994522000003');
    $device = makeOwnedDevice($owner, 'GF-OWN-3', '+994701410003'); // no coordinates

    $this->actingAs($owner, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 150,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'geofence_device_location_missing');
});

it('lets the owner disable their geofence', function () {
    $owner = makeUser('+994522000004');
    $device = makeOwnedDevice($owner, 'GF-OWN-4', '+994701410004');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0, 'geofence_enabled' => true, 'geofence_radius_m' => 150]);

    $this->actingAs($owner, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", ['geofence_enabled' => false])
        ->assertOk()
        ->assertJsonPath('geofence_enabled', false)
        ->assertJsonPath('geofence_radius_m', null);

    expect($device->refresh()->geofence_radius_m)->toBeNull();
});

it('forbids a roster member who is not the owner from configuring the geofence', function () {
    $owner = makeUser('+994522000005');
    $member = makeUser('+994522000006');
    $device = makeOwnedDevice($owner, 'GF-OWN-5', '+994701410005');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);
    makeDeviceUser($member, $device, 'user'); // active member, not owner

    $this->actingAs($member, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 150,
        ])
        ->assertStatus(403);

    expect($device->refresh()->geofence_enabled)->toBeFalse();
});

it('hides the geofence endpoint behind a 404 for a non-member', function () {
    $owner = makeUser('+994522000007');
    $stranger = makeUser('+994522000008');
    $device = makeOwnedDevice($owner, 'GF-OWN-6', '+994701410006');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);

    $this->actingAs($stranger, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 150,
        ])
        ->assertStatus(404);
});

it('ignores coordinate and ownership fields the owner tries to smuggle in', function () {
    $owner = makeUser('+994522000009');
    $device = makeOwnedDevice($owner, 'GF-OWN-7', '+994701410007');
    $device->update(['latitude' => 40.0, 'longitude' => 49.0]);
    $originalOwnerId = (int) $device->owner_user_id;

    $this->actingAs($owner, 'user')
        ->patchJson("/v1/devices/{$device->id}/geofence", [
            'geofence_enabled' => true,
            'geofence_radius_m' => 100,
            'latitude' => 0.0,          // must be ignored
            'longitude' => 0.0,         // must be ignored
            'owner_user_id' => 999999,  // must be ignored
        ])
        ->assertOk();

    $device->refresh();
    expect((float) $device->latitude)->toBe(40.0)
        ->and((float) $device->longitude)->toBe(49.0)
        ->and((int) $device->owner_user_id)->toBe($originalOwnerId)
        ->and($device->geofence_enabled)->toBeTrue()
        ->and($device->geofence_radius_m)->toBe(100);
});
