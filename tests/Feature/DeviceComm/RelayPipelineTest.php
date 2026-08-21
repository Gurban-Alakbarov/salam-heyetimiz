<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\DeviceComm\Adapters\FakeTraccarClient;
use App\Domain\DeviceComm\Drivers\TraccarDriver;
use App\Domain\DeviceComm\DTOs\TraccarCommandResult;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use Illuminate\Support\Str;

/*
| Phase 4 — VL110C relay through the existing DeviceComm open pipeline. Command text is model-driven
| (device_models.open_command / close_command by direction), and GT06 actuation is confirmed by polling
| Traccar's commandResult events for the device's 0x21 response text ("…Success!").
*/

beforeEach(function () {
    // No real waiting between polls in tests.
    config()->set('domain.device_comm.command_result_poll_interval_ms', 0);
    config()->set('domain.device_comm.command_result_poll_attempts', 3);
});

/**
 * Configure a device's model as a GT06-style confirm-via-commandResult relay model.
 * $pulseMs = null keeps the plain latching open (single command); a value makes the open a
 * momentary pulse (activate → hold → release).
 */
function relayModel(App\Domain\Devices\Models\Device $device, bool $confirm = true, string $open = 'RELAY,1#', string $close = 'RELAY,0#', ?int $pulseMs = null): App\Domain\Devices\Models\Device
{
    $device->update(['driver_type' => 'traccar']);
    $device->model->update([
        'open_command' => $open,
        'close_command' => $close,
        'command_result_confirm' => $confirm,
        'open_pulse_ms' => $pulseMs,
    ]);
    $device->load('model');

    return $device;
}

it('confirms an open via the VL110C commandResult success text and uses the model open_command', function () {
    [$device, $user, $du] = openableWithRoster('+994551500001', 'RLY-1', '+994700115001');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-1');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-1'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeTrue()
        ->and($result->actuated)->toBeTrue()
        ->and($result->metadata['command'])->toBe('RELAY,1#')
        ->and($result->metadata['response'])->toContain('Success!')
        ->and(app(FakeTraccarClient::class)->lastCommand())->toBe('RELAY,1#');
});

it('sends the model close_command for a close direction', function () {
    [$device, $user, $du] = openableWithRoster('+994551500002', 'RLY-2', '+994700115002');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-2');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-2'));
    app(FakeTraccarClient::class)->willRespondWith('Restore fuel supply: Success!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'close']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->actuated)->toBeTrue()
        ->and($result->metadata['command'])->toBe('RELAY,0#')
        ->and(app(FakeTraccarClient::class)->lastCommand())->toBe('RELAY,0#');
});

it('treats the idempotent "is not running" response as success', function () {
    [$device, $user, $du] = openableWithRoster('+994551500003', 'RLY-3', '+994700115003');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-3');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-3'));
    app(FakeTraccarClient::class)->willRespondWith('Already in the state of fuel supply to resume, the command is not running!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->actuated)->toBeTrue();
});

it('marks the command failed when the device returns a non-success response', function () {
    [$device, $user, $du] = openableWithRoster('+994551500004', 'RLY-4', '+994700115004');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-4');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-4'));
    app(FakeTraccarClient::class)->willRespondWith('Relay error: device busy');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeFalse()
        ->and($result->actuated)->toBeFalse()
        ->and($result->failureReason)->toBe('Relay error: device busy');
});

it('ignores a stale commandResult and confirms on the fresh one (id baseline)', function () {
    [$device, $user, $du] = openableWithRoster('+994551500005', 'RLY-5', '+994700115005');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-5');
    $fake = app(FakeTraccarClient::class);
    $fake->seedCommandResult('Restore fuel supply: Success!'); // stale, from a previous press
    $fake->willReturn(TraccarCommandResult::sent('cmd-5'));
    $fake->willRespondWith('Cut off the fuel supply: Success!'); // the fresh response for THIS send

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->actuated)->toBeTrue()
        ->and($result->metadata['response'])->toBe('Cut off the fuel supply: Success!');
});

it('does not poll commandResults for a non-confirm model and terminates at dispatched', function () {
    [$device, $user, $du] = openableWithRoster('+994551500006', 'RLY-6', '+994700115006');
    relayModel($device, confirm: false, open: 'OUTPUT0=1', close: 'OUTPUT0=0');
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-6');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-6'));
    app(FakeTraccarClient::class)->willRespondWith('should be ignored');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeTrue()
        ->and($result->actuated)->toBeFalse()
        ->and($result->metadata['command'])->toBe('OUTPUT0=1');
});

/*
| Admin "Test Relay" endpoint — POST /admin/v1/devices/{id}/relay (commands.test). Reuses the whole
| pipeline (source=admin, no roster gate). QUEUE_CONNECTION=sync → the dispatch job runs inline.
*/

it('fires an admin open relay through the pipeline and reaches opened with the response text', function () {
    $admin = makeSuperAdmin();
    [$device] = openableWithRoster('+994551510001', 'RLY-A1', '+994700115101');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-A1');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-a1'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/relay", ['action' => 'open'])
        ->assertStatus(202)
        ->assertJsonPath('direction', 'open')
        ->assertJsonPath('source', 'admin');

    $command = OpenCommand::query()->where('device_id', $device->id)->latest('id')->firstOrFail();
    expect($command->state)->toBe(OpenCommandState::Opened)
        ->and($command->source->value)->toBe('admin')
        ->and($command->direction->value)->toBe('open')
        ->and($command->user_id)->toBeNull()
        ->and($command->metadata['command'])->toBe('RELAY,1#')
        ->and($command->metadata['response'])->toContain('Success!')
        ->and(app(FakeTraccarClient::class)->lastCommand())->toBe('RELAY,1#');
});

it('shows the admin relay command in the command history with the new fields', function () {
    $admin = makeSuperAdmin();
    [$device] = openableWithRoster('+994551510002', 'RLY-A2', '+994700115102');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-A2');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-a2'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/relay", ['action' => 'open'])
        ->assertStatus(202);

    $this->actingAs($admin, 'admin')
        ->getJson("/admin/v1/devices/{$device->id}/commands")
        ->assertOk()
        ->assertJsonPath('data.0.direction', 'open')
        ->assertJsonPath('data.0.state', 'opened')
        ->assertJsonPath('data.0.command_text', 'RELAY,1#')
        ->assertJsonStructure([
            'data' => [['id', 'device_id', 'state', 'direction', 'source', 'command_text', 'terminal_response', 'latency_ms']],
        ]);
});

it('records relay_open_requested and relay_open_success audit events', function () {
    $admin = makeSuperAdmin();
    [$device] = openableWithRoster('+994551510003', 'RLY-A3', '+994700115103');
    relayModel($device);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-RLY-A3');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-a3'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/relay", ['action' => 'open'])
        ->assertStatus(202);

    expect(AuditLog::query()->where('action', 'relay_open_requested')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'relay_open_success')->exists())->toBeTrue();
});

it('rejects an invalid relay action', function () {
    $admin = makeSuperAdmin();
    [$device] = openableWithRoster('+994551510004', 'RLY-A4', '+994700115104');
    relayModel($device);

    $this->actingAs($admin, 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/relay", ['action' => 'toggle'])
        ->assertStatus(422);
});

it('requires an admin token for the relay endpoint', function () {
    [$device] = openableWithRoster('+994551510005', 'RLY-A5', '+994700115105');

    $this->postJson("/admin/v1/devices/{$device->id}/relay", ['action' => 'open'])->assertStatus(401);
});

/*
| Auto-release open (barrier). The VL110C relay LATCHES on RELAY,1#, so one "open" is activate → wait
| for the device to CONFIRM it executed → release (RELAY,0#). Both commands are issued by the driver,
| so the API contract and the mobile/admin callers still send a single open.
|
| The release is anchored to the CONFIRMATION, never the send: RELAY,1#'s speed interlock defers the
| cut (measured 15.7 s with a GPS fix vs 5 s without) and a release that lands first CANCELS the cut.
*/

it('auto-releases once the open is confirmed executed: RELAY,1# then RELAY,0#', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520001', 'PLS-1', '+994700115201');
    relayModel($device, pulseMs: 0); // 0 = release the moment the device confirms
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-1');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p1'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    // Both physical commands, in order, from ONE open.
    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))
        ->toBe(['RELAY,1#', 'RELAY,0#']);

    // The open still resolves on the activate's confirmation, and both legs are recorded.
    expect($result->actuated)->toBeTrue()
        ->and($result->metadata['command'])->toBe('RELAY,1#')
        ->and($result->metadata['response'])->toContain('Cut off')
        ->and($result->metadata['auto_release_hold_ms'])->toBe(0)
        ->and($result->metadata['release_command'])->toBe('RELAY,0#')
        ->and($result->metadata['release_sent'])->toBeTrue()
        ->and($result->metadata['release_failure'])->toBeNull();

    Illuminate\Support\Sleep::assertNeverSlept(); // hold 0 → no wait after the confirmation
});

it('honours an extra hold after the confirmation before releasing', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520007', 'PLS-7', '+994700115207');
    relayModel($device, pulseMs: 1500);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-7');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p7'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#', 'RELAY,0#'])
        ->and($result->metadata['auto_release_hold_ms'])->toBe(1500);
    Illuminate\Support\Sleep::assertSlept(fn (Carbon\CarbonInterval $d) => (int) $d->totalMilliseconds === 1500);
});

it('releases an already-latched relay too (device replies "is not running")', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520008', 'PLS-8', '+994700115208');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-8');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p8'));
    app(FakeTraccarClient::class)->willRespondWith('Already in the state of fuel supply cut off, the command is not running!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    // Idempotent "already cut" still means the relay IS latched → it must be released.
    expect($result->actuated)->toBeTrue()
        ->and(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#', 'RELAY,0#']);
});

it('does NOT release when the device reports the open did not execute', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520009', 'PLS-9', '+994700115209');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-9');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p9'));
    app(FakeTraccarClient::class)->willRespondWith('cancelled the command of cutting-off fuel supply; fuel supply is on.');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    // Nothing latched → nothing to release.
    expect($result->actuated)->toBeFalse()
        ->and(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#']);
});

it('does NOT release when the open is never confirmed (no device response)', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520010', 'PLS-10', '+994700115210');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-10');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p10'));
    app(FakeTraccarClient::class)->willRespondWith(null); // device stays silent

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect($result->dispatched)->toBeTrue()
        ->and($result->actuated)->toBeFalse()
        ->and(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#']);
});

it('does NOT send the release when the activate fails to send', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520002', 'PLS-2', '+994700115202');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-2');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::failed('traccar_error'));

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    // Nothing latched → nothing to release: the activate is the ONLY command sent.
    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#'])
        ->and($result->dispatched)->toBeFalse()
        ->and($result->failureReason)->toBe('traccar_error')
        ->and($result->metadata)->not->toHaveKey('release_command');
});

it('does NOT send the release when the device is offline (activate queued)', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520003', 'PLS-3', '+994700115203');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-3');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::queued());

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,1#'])
        ->and($result->failureReason)->toBe('device_offline');
});

it('keeps the single-command open for models opted out of auto-release (UMKa self-clears on-device)', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520004', 'PLS-4', '+994700115204');
    relayModel($device, confirm: false, open: 'OUTPUT0=1', close: 'OUTPUT0=0', pulseMs: null);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-4');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p4'));

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'open']);
    $result = app(TraccarDriver::class)->open($device, $command);

    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['OUTPUT0=1'])
        ->and($result->metadata)->not->toHaveKey('release_command');
    Illuminate\Support\Sleep::assertNeverSlept();
});

it('never auto-releases a close — a close is a single command', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520005', 'PLS-5', '+994700115205');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-5');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p5'));
    app(FakeTraccarClient::class)->willRespondWith('Restore fuel supply: Success!');

    $command = makeOpenCommand($device, $user, $du, ['direction' => 'close']);
    app(TraccarDriver::class)->open($device, $command);

    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))->toBe(['RELAY,0#']);
});

it('auto-releases through the full mobile open pipeline without any API change', function () {
    Illuminate\Support\Sleep::fake();
    [$device, $user, $du] = openableWithRoster('+994551520006', 'PLS-6', '+994700115206');
    relayModel($device, pulseMs: 0);
    app(TraccarDeviceMapper::class)->register($device, 'IMEI-PLS-6');
    app(FakeTraccarClient::class)->willReturn(TraccarCommandResult::sent('cmd-p6'));
    app(FakeTraccarClient::class)->willRespondWith('Cut off the fuel supply: Success!');

    // Exactly what the mobile app calls today — unchanged contract, one request.
    $res = $this->actingAs($user, 'user')->postJson("/v1/devices/{$device->id}/open", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(202);

    app(App\Domain\DeviceComm\Services\CommandDispatcher::class)
        ->dispatch(OpenCommand::findOrFail($res->json('command_id')));

    expect(array_column(app(FakeTraccarClient::class)->sentCommands, 'command'))
        ->toBe(['RELAY,1#', 'RELAY,0#'])
        ->and(OpenCommand::findOrFail($res->json('command_id'))->state)
        ->toBe(OpenCommandState::Opened);
});
