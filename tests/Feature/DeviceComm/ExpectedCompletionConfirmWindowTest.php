<?php

use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\DeviceComm\Services\ExpectedCompletionEstimator;
use App\Domain\Devices\Enums\DriverType;
use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Support\Facades\Queue;

/*
| Y2 — VL110C confirms an open by polling the device's own commandResult (reply ~15s later, window up to
| 15×1.5s = 22.5s). The app sizes its status polling on expected_completion_ms (ceil(ms/1200)+3 polls; the home
| widget +2), so for such a model the estimate always covers the whole poll window plus the send (25.5s):
| a slow-but-successful open is never shown as a timeout. Other models keep their estimate unchanged.
*/

function y2ConfirmModel(): DeviceModel
{
    return DeviceModel::query()->firstOrCreate(
        ['vendor' => 'Jimi', 'model_code' => 'VL110C-Y2'],
        ['whitelist_capacity' => 100, 'default_driver_type' => 'traccar', 'command_result_confirm' => true],
    );
}

it('a confirm-via-result model (VL110C) is estimated over its whole confirmation window', function () {
    Queue::fake();
    $user = makeUser('+994509660001');
    $device = makeOpenableDevice($user, 'SER-Y2-1', '+994700660101');
    $device->forceFill(['device_model_id' => y2ConfirmModel()->id])->save();

    $ms = (int) $this->actingAs($user, 'user')
        ->postJson("/v1/devices/{$device->id}/open", [], ['Idempotency-Key' => 'y2-open-1'])
        ->assertStatus(202)->json('expected_completion_ms');

    expect($ms)->toBe(15 * 1500 + 3000)
        // the app's poll budget (1.2s interval, +3 polls) now outlasts the server's 22.5s confirmation window
        ->and((int) (ceil($ms / 1200) + 3) * 1200)->toBeGreaterThan(22_500)
        // the home widget's budget (+2 polls) too
        ->and((int) (ceil($ms / 1200) + 2) * 1200)->toBeGreaterThan(22_500);
});

it('a confirm-via-result model keeps the floor even with fast history; a slower history wins', function () {
    $user = makeUser('+994509660002');
    $device = makeOpenableDevice($user, 'SER-Y2-2', '+994700660102');
    $device->forceFill(['device_model_id' => y2ConfirmModel()->id])->save();
    $du = DeviceUser::query()->where('device_id', $device->id)->firstOrFail();
    $estimator = app(ExpectedCompletionEstimator::class);

    foreach (range(1, 12) as $i) {
        makeOpenCommand($device, $user, $du, ['state' => 'opened', 'latency_ms' => 16_000]);
    }
    expect($estimator->estimate($device->fresh(), DriverType::Traccar))->toBe(25_500);

    foreach (range(1, 12) as $i) {
        makeOpenCommand($device, $user, $du, ['state' => 'dispatched', 'latency_ms' => 30_000]);
    }
    expect($estimator->estimate($device->fresh(), DriverType::Traccar))->toBe(30_000);
});

it('other models keep their estimate (default and p90 unchanged)', function () {
    $user = makeUser('+994509660003');
    $device = makeOpenableDevice($user, 'SER-Y2-3', '+994700660103'); // plain test model: no result confirmation
    $du = DeviceUser::query()->where('device_id', $device->id)->firstOrFail();
    $estimator = app(ExpectedCompletionEstimator::class);

    expect($estimator->estimate($device, DriverType::Traccar))->toBe(3000);

    foreach (range(1, 10) as $i) {
        makeOpenCommand($device, $user, $du, ['state' => 'opened', 'latency_ms' => 1000 * $i]);
    }
    expect($estimator->estimate($device->fresh(), DriverType::Traccar))->toBe(9000);
});
