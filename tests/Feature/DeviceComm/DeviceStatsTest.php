<?php

use App\Domain\Roster\Models\DeviceUser;

/*
| GET /v1/devices/{id}/stats — getDeviceStats (open_commands aggregation).
*/

it('aggregates open statistics over the period', function () {
    $owner = makeUser('+994509400001');
    $device = makeOpenableDevice($owner, 'SER-ST1', '+994700094001');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();

    foreach ([1000, 2000, 3000] as $latency) {
        makeOpenCommand($device, $owner, $du, ['state' => 'dispatched', 'latency_ms' => $latency, 'completed_at' => now()]);
    }
    makeOpenCommand($device, $owner, $du, ['state' => 'opened', 'latency_ms' => 4000, 'completed_at' => now()]);
    makeOpenCommand($device, $owner, $du, ['state' => 'failed', 'failure_reason' => 'device_offline']);

    $response = $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}/stats?period=30d");

    $response->assertOk()
        ->assertJsonPath('period', '30d')
        ->assertJsonPath('opens_total', 5)
        ->assertJsonPath('opens_success', 4)
        ->assertJsonPath('opens_failed', 1)
        ->assertJsonPath('success_rate', 0.8)
        ->assertJsonPath('avg_latency_ms', 2500); // (1000+2000+3000+4000)/4

    expect($response->json('last_open_at'))->not->toBeNull();
});

it('returns zeroes for a device with no opens', function () {
    $owner = makeUser('+994509400002');
    $device = makeOpenableDevice($owner, 'SER-ST2', '+994700094002');

    $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}/stats")
        ->assertOk()
        ->assertJsonPath('opens_total', 0)
        ->assertJsonPath('success_rate', 0)
        ->assertJsonPath('avg_latency_ms', null)
        ->assertJsonPath('last_open_at', null);
});

it('hides stats for a device the caller is not on', function () {
    $owner = makeUser('+994509400003');
    $stranger = makeUser('+994509400004');
    $device = makeOpenableDevice($owner, 'SER-ST3', '+994700094003');

    $this->actingAs($stranger, 'user')->getJson("/v1/devices/{$device->id}/stats")->assertStatus(404);
});
