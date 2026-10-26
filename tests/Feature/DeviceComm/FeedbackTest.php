<?php

use App\Domain\DeviceComm\Models\OpenCommandFeedback;
use App\Domain\Roster\Models\DeviceUser;

/*
| POST /v1/commands/{id}/feedback — submitOpenFeedback (CRIT-06; one per command/user; replay → 200).
*/

it('records actuation feedback once and replays it on a second submit', function () {
    $owner = makeUser('+994509300001');
    $device = makeOpenableDevice($owner, 'SER-F1', '+994700093001');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $command = makeOpenCommand($device, $owner, $du, ['state' => 'dispatched']);

    $this->actingAs($owner, 'user')->postJson("/v1/commands/{$command->id}/feedback", [
        'gate_moved' => true,
        'comment' => 'worked',
    ])->assertStatus(201);

    expect(OpenCommandFeedback::query()->where('open_command_id', $command->id)->where('user_id', $owner->id)->where('gate_moved', true)->count())->toBe(1);

    // Replay → 200, still a single row.
    $this->actingAs($owner, 'user')->postJson("/v1/commands/{$command->id}/feedback", [
        'gate_moved' => false,
    ])->assertStatus(200);

    expect(OpenCommandFeedback::query()->where('open_command_id', $command->id)->count())->toBe(1);
});

it('validates the body', function () {
    $owner = makeUser('+994509300002');
    $device = makeOpenableDevice($owner, 'SER-F2', '+994700093002');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $command = makeOpenCommand($device, $owner, $du);

    $this->actingAs($owner, 'user')->postJson("/v1/commands/{$command->id}/feedback", [])->assertStatus(422);
});

it('hides another user’s command behind a 404', function () {
    $owner = makeUser('+994509300003');
    $stranger = makeUser('+994509300004');
    $device = makeOpenableDevice($owner, 'SER-F3', '+994700093003');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $command = makeOpenCommand($device, $owner, $du);

    $this->actingAs($stranger, 'user')->postJson("/v1/commands/{$command->id}/feedback", [
        'gate_moved' => true,
    ])->assertStatus(404);
});

it('takes feedback only for a command that reached the barrier (A6)', function (string $state, int $status) {
    $owner = makeUser('+994509300010');
    $device = makeOpenableDevice($owner, 'SER-F6', '+994700093010');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $command = makeOpenCommand($device, $owner, $du, ['state' => $state]);

    $res = $this->actingAs($owner, 'user')->postJson("/v1/commands/{$command->id}/feedback", ['gate_moved' => false])->assertStatus($status);

    if ($status === 409) {
        $res->assertJsonPath('error.code', 'feedback_not_applicable');
        expect(OpenCommandFeedback::query()->where('open_command_id', $command->id)->count())->toBe(0);
    }
})->with([
    'dispatched' => ['dispatched', 201],
    'opened' => ['opened', 201],
    'failed' => ['failed', 409],
    'expired' => ['expired', 409],
    'queued' => ['queued', 409],
]);
