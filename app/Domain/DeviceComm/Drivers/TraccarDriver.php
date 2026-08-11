<?php

namespace App\Domain\DeviceComm\Drivers;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Contracts\DeviceDriver;
use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\DTOs\DriverDiagnosticResult;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\DTOs\DriverSyncResult;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Primary transport (v1.2 / v1.3): sends the relay command over the device's live Traccar session via
 * the REST `custom` command. The command text is model-driven (device_models.open_command /
 * close_command by the command's direction — never hardcoded), so one driver serves UMKa (OUTPUT0=1/0)
 * and Jimi VL110C (RELAY,1#/RELAY,0#).
 *
 * OPEN AUTO-RELEASE: VL110C's `RELAY,1#` LATCHES the relay, so a barrier open must drop the latch
 * again. Models that opt in (`open_pulse_ms` not null) get it for free here: the driver sends the open,
 * waits for the device to CONFIRM it executed, then sends close_command — so the caller (mobile +
 * admin) still issues ONE open and the API contract is unchanged.
 *
 * The release is anchored to the CONFIRMATION, never to the send: `RELAY,1#` runs on the device's own
 * schedule (its speed interlock defers the cut by ~10 s when the unit has a GPS fix — measured 15.7 s
 * with a fix vs 5 s without), and a release that lands before the cut executes CANCELS it ("cancelled
 * the command of cutting-off fuel supply; fuel supply is on") — the barrier would never open. A failed
 * send or an unconfirmed open therefore never releases. Models opted out (UMKa: OUT0 self-clears
 * on-device) keep the single-command behaviour.
 *
 * Actuation confirmation is per model. For GT06/VL110C (command_result_confirm = true) the driver polls
 * Traccar's `commandResult` events for the device's 0x21 response text ("…Success!") and reaches the
 * terminal `opened` directly. For UMKa the command is `dispatched` and confirmed later by the
 * event-forward ingestion (output-bit read-back — R-GSM-03). An offline device yields a transient
 * failure so the dispatcher falls back to SMS (R-GSM-04).
 */
final class TraccarDriver implements DeviceDriver
{
    private ?\Psr\Log\LoggerInterface $relayLog = null;

    public function __construct(
        private readonly TraccarClient $traccar,
        private readonly TraccarDeviceMapper $mapper,
        private readonly Clock $clock,
    ) {}

    public function open(Device $device, OpenCommand $command): DriverOpenResult
    {
        $traccarId = $this->mapper->traccarIdFor($device);
        if ($traccarId === null) {
            return DriverOpenResult::failed('device_not_registered');
        }

        $direction = $command->direction ?? CommandDirection::Open;
        $commandText = $this->commandText($device, $direction);
        if ($commandText === null || $commandText === '') {
            return DriverOpenResult::failed('command_not_configured');
        }

        $confirmViaResult = (bool) ($device->model?->command_result_confirm ?? false);
        $lookback = (int) config('domain.device_comm.command_result_lookback_hours', 12);

        // Baseline the newest existing commandResult id BEFORE sending, so we only accept a fresher
        // response (correlating by id is skew-proof — the VL110C on-device clock can be hours off).
        $baselineId = $confirmViaResult ? $this->latestResultId($traccarId, $lookback) : 0;

        $startedAt = $this->clock->now();
        $result = $this->traccar->sendCommand($traccarId, $commandText);

        $this->logSend($device, $command, 'activate', $commandText, $result);

        if (! $result->sent) {
            $reason = $result->queued ? 'device_offline' : ($result->failureReason ?? 'traccar_error');

            // The activate never reached the device — nothing latched, so NO release is sent.
            return DriverOpenResult::failed($reason, metadata: ['command' => $commandText]);
        }

        // Fire-and-forget models (UMKa/Wialon): we never learn when the relay actually moved, so there
        // is nothing to auto-release against (OUT0 self-clears on-device anyway).
        if (! $confirmViaResult) {
            return DriverOpenResult::dispatched(metadata: [
                'command' => $commandText,
                'traccar_command' => $result->reference,
            ]);
        }

        $outcome = $this->awaitCommandResult($traccarId, $commandText, $baselineId, $startedAt, $lookback, $result->reference);

        // Auto-release: the device CONFIRMED the open executed, so drop the latch now. Anchored to the
        // confirmation (not the send) because RELAY,1# executes on the device's own schedule — its
        // speed interlock defers the cut by ~10 s when the unit has a GPS fix, and a release issued
        // before that lands CANCELS the pending cut ("cancelled the command of cutting-off fuel
        // supply") instead of releasing it, leaving the barrier closed.
        if ($outcome->actuated) {
            $release = $this->autoRelease($device, $command, $direction, $traccarId);

            return $release === []
                ? $outcome
                : DriverOpenResult::opened($outcome->latencyMs, array_merge($outcome->metadata, $release));
        }

        // Not confirmed (failure text, or no response in the poll window) → nothing proven latched.
        return $outcome;
    }

    /**
     * Auto-release the latched open: send the model's close_command as soon as the open is CONFIRMED
     * executed, so a barrier does not stay held open. Runs ONLY for an open, ONLY on models that opt in
     * (`open_pulse_ms` not null), and ONLY from a confirmed-actuated open — a failed send or an
     * unconfirmed open never releases (nothing is proven latched).
     *
     * `open_pulse_ms` is an EXTRA hold on top of the confirmation: 0 = release the moment the device
     * reports the open executed (the contact still stays closed for the release's own round-trip,
     * ~2-5 s, which is the barrier's trigger pulse). The release is fire-and-forget — the open's
     * terminal state is already decided by its own confirmation.
     *
     * @return array<string, mixed> release metadata to merge into the command record (empty = none)
     */
    private function autoRelease(Device $device, OpenCommand $command, CommandDirection $direction, int $traccarId): array
    {
        if ($direction !== CommandDirection::Open) {
            return []; // a close is a single command — never auto-released
        }

        $holdMs = $device->model?->open_pulse_ms; // null = opt out (plain latching open)
        if ($holdMs === null) {
            return [];
        }

        $releaseCommand = $this->commandText($device, CommandDirection::Close);
        if ($releaseCommand === null || $releaseCommand === '') {
            return ['auto_release_skipped' => 'close_command_not_configured'];
        }

        if ($holdMs > 0) {
            Sleep::for($holdMs)->milliseconds(); // fakeable — tests assert the hold without waiting
        }

        $release = $this->traccar->sendCommand($traccarId, $releaseCommand);
        $this->logSend($device, $command, 'release', $releaseCommand, $release);

        return [
            'auto_release_hold_ms' => (int) $holdMs,
            'release_command' => $releaseCommand,
            'release_sent' => $release->sent,
            'release_traccar_command' => $release->reference,
            'release_failure' => $release->sent ? null : ($release->failureReason ?? ($release->queued ? 'device_offline' : 'traccar_error')),
        ];
    }

    /**
     * Dedicated relay log. On-demand channel (not the app stack) so every physical send is recorded at
     * info level regardless of the global LOG_LEVEL — production runs at `warning`, which would drop
     * these — and without flooding laravel.log.
     */
    private function relayLog(): \Psr\Log\LoggerInterface
    {
        return $this->relayLog ??= Log::build([
            'driver' => 'daily',
            'path' => storage_path('logs/devicecomm.log'),
            'level' => 'info',
            'days' => 14,
        ]);
    }

    /** One log line per physical command sent (activate + release are recorded separately). */
    private function logSend(Device $device, OpenCommand $command, string $phase, string $commandText, \App\Domain\DeviceComm\DTOs\TraccarCommandResult $result): void
    {
        $this->relayLog()->info('devicecomm.relay.'.$phase, [
            'open_command_id' => $command->getKey(),
            'device_id' => $device->getKey(),
            'direction' => ($command->direction ?? CommandDirection::Open)->value,
            'command' => $commandText,
            'sent' => $result->sent,
            'queued' => $result->queued,
            'failure_reason' => $result->failureReason,
            'traccar_command' => $result->reference,
        ]);
    }

    /**
     * Resolve the command text for a direction from the device model (open_command / close_command),
     * falling back to the global config default. Never hardcoded (Phase 4).
     */
    private function commandText(Device $device, CommandDirection $direction): ?string
    {
        $model = $device->model;
        $fromModel = $direction === CommandDirection::Close ? $model?->close_command : $model?->open_command;

        if (is_string($fromModel) && $fromModel !== '') {
            return $fromModel;
        }

        $config = $direction === CommandDirection::Close
            ? config('domain.device_comm.close_command')
            : config('domain.device_comm.open_command', 'OUTPUT0=1');

        return is_string($config) && $config !== '' ? $config : null;
    }

    /** Newest existing Traccar commandResult event id for the device (0 if none), for baselining. */
    private function latestResultId(int $traccarId, int $lookbackHours): int
    {
        $now = $this->clock->now();
        $events = $this->traccar->commandResults($traccarId, $now->subHours($lookbackHours), $now->addMinutes(5));

        return $events[0]['id'] ?? 0; // commandResults() returns newest-first
    }

    /**
     * Poll Traccar for the device's response to the command we just sent (0x21 → commandResult event
     * with id > baseline). Success markers → `opened`; a non-success response → `failed` (the response
     * text becomes the failure reason); no response within the window → `dispatched` (unconfirmed).
     */
    private function awaitCommandResult(
        int $traccarId,
        string $commandText,
        int $baselineId,
        CarbonImmutable $startedAt,
        int $lookbackHours,
        ?string $reference,
    ): DriverOpenResult {
        $attempts = max(1, (int) config('domain.device_comm.command_result_poll_attempts', 6));
        $intervalMs = max(0, (int) config('domain.device_comm.command_result_poll_interval_ms', 1500));

        for ($i = 0; $i < $attempts; $i++) {
            if ($intervalMs > 0) {
                usleep($intervalMs * 1000);
            }

            $now = $this->clock->now();
            $events = $this->traccar->commandResults($traccarId, $now->subHours($lookbackHours), $now->addMinutes(5));

            foreach ($events as $event) {
                if ($event['id'] <= $baselineId) {
                    continue; // stale — from a previous command
                }
                $text = $event['result'];
                if (! is_string($text) || $text === '') {
                    continue;
                }

                $latencyMs = $this->latencyMs($startedAt);
                $metadata = [
                    'command' => $commandText,
                    'response' => $text,
                    'traccar_event_id' => $event['id'],
                    'traccar_command' => $reference,
                ];

                return $this->isSuccess($text)
                    ? DriverOpenResult::opened($latencyMs, $metadata)
                    : DriverOpenResult::failed(mb_substr($text, 0, 120), $latencyMs, $metadata);
            }
        }

        return DriverOpenResult::dispatched($this->latencyMs($startedAt), metadata: [
            'command' => $commandText,
            'traccar_command' => $reference,
            'note' => 'no_command_result',
        ]);
    }

    private function isSuccess(string $text): bool
    {
        $markers = (array) config('domain.device_comm.command_result_success_markers', ['Success!']);
        foreach ($markers as $marker) {
            if (is_string($marker) && $marker !== '' && stripos($text, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private function latencyMs(CarbonImmutable $startedAt): int
    {
        return (int) abs($this->clock->now()->diffInMilliseconds($startedAt));
    }

    /**
     * Under the Traccar/server-authorised model the device holds no per-user whitelist (authorisation is
     * platform-side), so add/remove are no-ops that complete the outbox. (Retained for BLE/credential
     * provisioning if it returns later.)
     */
    public function whitelistAdd(Device $device, string $phone): DriverSyncResult
    {
        return DriverSyncResult::ok();
    }

    public function whitelistRemove(Device $device, string $phone): DriverSyncResult
    {
        return DriverSyncResult::ok();
    }

    public function diagnose(Device $device): DriverDiagnosticResult
    {
        $threshold = $this->clock->now()->subHours((int) config('domain.devices.offline_threshold_hours', 24));
        $online = $device->last_online_at !== null && $device->last_online_at->greaterThan($threshold);

        return new DriverDiagnosticResult(online: $online, signalStrength: $device->last_signal_strength);
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, ['open', 'actuation_confirmation', 'diagnose'], true);
    }
}
