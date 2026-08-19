<?php

use App\Domain\DeviceComm\Jobs\DispatchOpenCommandJob;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Devices\Models\Device;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use Illuminate\Support\Facades\Queue;

/*
| GEOFENCE-1 — POST /v1/devices/{id}/open distance gate. The server holds the device coords + radius
| and never trusts a client-supplied distance/radius. The gate runs AFTER core authorization
| (roster + R-DOM-05) and BEFORE the cooldown/issue. Device fixture: (40.0, 49.0), radius 200 m.
| One degree of longitude at 40° N ≈ 85 180 m, so +0.004° ≈ 341 m and +0.01° ≈ 852 m.
*/

/** Enable the geofence directly on the row (bypasses the config action) at (40.0, 49.0) / 200 m. */
function enableGeofence(Device $device, int $radius = 200): void
{
    $device->update([
        'latitude' => 40.0,
        'longitude' => 49.0,
        'geofence_enabled' => true,
        'geofence_radius_m' => $radius,
    ]);
}

it('opens when the caller is inside the radius', function () {
    Queue::fake();
    $user = makeUser('+994521000001');
    $device = makeOpenableDevice($user, 'SER-GF1', '+994700095001');
    enableGeofence($device);

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.0,
        ], ['Idempotency-Key' => 'gf-in-1'])
        ->assertStatus(202)
        ->assertJsonPath('state', 'queued');

    Queue::assertPushed(DispatchOpenCommandJob::class);
});

it('rejects a caller clearly outside the radius with outside_geofence', function () {
    Queue::fake();
    $user = makeUser('+994521000002');
    $device = makeOpenableDevice($user, 'SER-GF2', '+994700095002');
    enableGeofence($device);

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.01, // ≈ 852 m east → outside 200 m
        ], ['Idempotency-Key' => 'gf-out-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'outside_geofence');

    expect(OpenCommand::query()->where('device_id', $device->id)->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('requires a location when the geofence is enabled', function () {
    Queue::fake();
    $user = makeUser('+994521000003');
    $device = makeOpenableDevice($user, 'SER-GF3', '+994700095003');
    enableGeofence($device);

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'gf-noloc-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'location_required');

    expect(OpenCommand::query()->where('device_id', $device->id)->count())->toBe(0);
});

it('does not require a location when the geofence is off (regression)', function () {
    Queue::fake();
    $user = makeUser('+994521000004');
    $device = makeOpenableDevice($user, 'SER-GF4', '+994700095004');
    // geofence stays off (default) — no location attached.

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'gf-off-1'])
        ->assertStatus(202)
        ->assertJsonPath('state', 'queued');

    Queue::assertPushed(DispatchOpenCommandJob::class);
});

it('rejects a fix whose accuracy is larger than the radius with location_imprecise', function () {
    Queue::fake();
    $user = makeUser('+994521000005');
    $device = makeOpenableDevice($user, 'SER-GF5', '+994700095005');
    enableGeofence($device); // radius 200

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.0,
            'accuracy' => 201, // just over the radius → too imprecise to trust (D4)
        ], ['Idempotency-Key' => 'gf-imp-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'location_imprecise');
});

it('accepts a fix whose accuracy equals the radius (D4 threshold boundary)', function () {
    Queue::fake();
    $user = makeUser('+994521000006');
    $device = makeOpenableDevice($user, 'SER-GF6', '+994700095006');
    enableGeofence($device); // radius 200

    // accuracy == radius is NOT > radius, so it is allowed; at the device point the zone easily contains it.
    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.0,
            'accuracy' => 200,
        ], ['Idempotency-Key' => 'gf-bound-1'])
        ->assertStatus(202)
        ->assertJsonPath('state', 'queued');
});

it('lets the accuracy margin bring a borderline-outside caller inside', function () {
    Queue::fake();
    $user = makeUser('+994521000007');
    $device = makeOpenableDevice($user, 'SER-GF7', '+994700095007');
    enableGeofence($device); // radius 200

    // ≈341 m east: raw distance is outside 200 m, but distance − accuracy(200) ≈ 141 ≤ 200 → inside.
    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.004,
            'accuracy' => 200,
        ], ['Idempotency-Key' => 'gf-margin-1'])
        ->assertStatus(202)
        ->assertJsonPath('state', 'queued');
});

it('rejects the same borderline caller without an accuracy margin', function () {
    Queue::fake();
    $user = makeUser('+994521000008');
    $device = makeOpenableDevice($user, 'SER-GF8', '+994700095008');
    enableGeofence($device); // radius 200

    // ≈341 m east, no accuracy → margin 0 → outside 200 m.
    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.004,
        ], ['Idempotency-Key' => 'gf-margin-2'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'outside_geofence');
});

it('fails closed when the geofence is enabled but the device has no coordinates', function () {
    Queue::fake();
    $user = makeUser('+994521000009');
    $device = makeOpenableDevice($user, 'SER-GF9', '+994700095009');
    // Legacy/misconfigured row: enabled with a radius but no coordinates (the config action forbids this,
    // so we set it directly). The open path must refuse rather than let everyone through.
    $device->update([
        'latitude' => null,
        'longitude' => null,
        'geofence_enabled' => true,
        'geofence_radius_m' => 200,
    ]);

    $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [
            'latitude' => 40.0,
            'longitude' => 49.0,
        ], ['Idempotency-Key' => 'gf-misconf-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

it('enforces subscription (R-DOM-05) before the geofence gate', function () {
    Queue::fake();
    $owner = makeUser('+994521000010');
    $member = makeUser('+994521000011');
    $device = makeOpenableDevice($owner, 'SER-GF10', '+994700095010');
    enableGeofence($device);

    $memberDu = makeDeviceUser($member, $device, 'user');
    makeSubscription($memberDu, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    // The member sends NO location. If the geofence ran first this would be location_required;
    // because R-DOM-05 runs first, it must be subscription_required.
    $this->actingAs($member, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'gf-order-1'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'subscription_required');
});

it('hides a non-member behind a 404 even with the geofence on', function () {
    Queue::fake();
    $owner = makeUser('+994521000012');
    $stranger = makeUser('+994521000013');
    $device = makeOpenableDevice($owner, 'SER-GF11', '+994700095011');
    enableGeofence($device);

    $this->actingAs($stranger, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'gf-404-1'])
        ->assertStatus(404);
});
