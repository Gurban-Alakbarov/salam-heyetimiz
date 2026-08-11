<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Models\OpenCommandAttempt;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;

/**
 * Owns the open_commands aggregate and its lifecycle state machine (R-GSM-06): queued → dispatching →
 * {dispatched|opened|failed|expired}. Each open writes exactly one open_commands row plus one or more
 * open_command_attempts. Transitions are idempotent — a terminal command is never re-transitioned.
 */
final class OpenCommandService
{
    public function __construct(private readonly Clock $clock) {}

    public function issue(
        Device $device,
        User $user,
        DeviceUser $deviceUser,
        ?Subscription $subscription,
        DriverType $driver,
        ?string $idempotencyKey,
        OpenCommandSource $source,
        ?string $clientAppVersion,
        ?string $clientIp,
        CommandDirection $direction = CommandDirection::Open,
    ): OpenCommand {
        $now = $this->clock->now();

        return OpenCommand::query()->create([
            'partition_key' => (int) $now->format('Ym'),
            'device_id' => $device->getKey(),
            'user_id' => $user->getKey(),
            'device_user_id' => $deviceUser->getKey(),
            'subscription_id' => $subscription?->getKey(),
            'idempotency_key' => $idempotencyKey,
            'driver' => $driver->value,
            'direction' => $direction->value,
            'state' => OpenCommandState::Queued->value,
            'attempts' => 0,
            'requested_at' => $now,
            'source' => $source->value,
            'client_app_version' => $clientAppVersion,
            'client_ip' => $clientIp,
            'created_at' => $now,
        ]);
    }

    /**
     * Issue a relay command not tied to a roster user (admin "Test Relay"). Reuses the whole downstream
     * pipeline (DispatchOpenCommandJob → CommandDispatcher → driver → state machine); only the entry
     * point differs — no roster/subscription/cooldown gate. source = admin.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function issueAdmin(
        Device $device,
        DriverType $driver,
        CommandDirection $direction,
        ?string $clientIp,
        array $metadata = [],
    ): OpenCommand {
        $now = $this->clock->now();

        return OpenCommand::query()->create([
            'partition_key' => (int) $now->format('Ym'),
            'device_id' => $device->getKey(),
            'user_id' => null,
            'device_user_id' => null,
            'subscription_id' => null,
            'idempotency_key' => null,
            'driver' => $driver->value,
            'direction' => $direction->value,
            'state' => OpenCommandState::Queued->value,
            'attempts' => 0,
            'requested_at' => $now,
            'source' => OpenCommandSource::Admin->value,
            'client_ip' => $clientIp,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => $now,
        ]);
    }

    /**
     * Issue a relay command authorised by a visitor link (no roster user). Reuses the whole downstream
     * pipeline — identical to a mobile/admin open — only the entry point differs. source = visitor;
     * `metadata.visitor_link_id` lets the usage listener attribute the completion to the link.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function issueVisitor(
        Device $device,
        DriverType $driver,
        CommandDirection $direction,
        ?string $clientIp,
        array $metadata = [],
    ): OpenCommand {
        $now = $this->clock->now();

        return OpenCommand::query()->create([
            'partition_key' => (int) $now->format('Ym'),
            'device_id' => $device->getKey(),
            'user_id' => null,
            'device_user_id' => null,
            'subscription_id' => null,
            'idempotency_key' => null,
            'driver' => $driver->value,
            'direction' => $direction->value,
            'state' => OpenCommandState::Queued->value,
            'attempts' => 0,
            'requested_at' => $now,
            'source' => OpenCommandSource::Visitor->value,
            'client_ip' => $clientIp,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => $now,
        ]);
    }

    public function findByIdempotencyKey(int $userId, string $key): ?OpenCommand
    {
        return OpenCommand::query()
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();
    }

    public function markDispatching(OpenCommand $command): void
    {
        if ($command->state !== OpenCommandState::Queued) {
            return;
        }

        $command->forceFill(['state' => OpenCommandState::Dispatching->value])->save();
    }

    public function incrementAttempts(OpenCommand $command): void
    {
        $command->increment('attempts');
    }

    /**
     * CLIP-style terminal success — reached the device, actuation not observable (R-GSM-03).
     *
     * @param  array<string, mixed>  $metadata  merged into the command's metadata (command text, etc.)
     */
    public function markDispatched(OpenCommand $command, ?int $latencyMs, array $metadata = []): void
    {
        $this->finalize($command, OpenCommandState::Dispatched, $latencyMs, null, $metadata);
    }

    /**
     * Confirmed terminal success — only for drivers that observe actuation.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function markOpened(OpenCommand $command, ?int $latencyMs, array $metadata = []): void
    {
        $this->finalize($command, OpenCommandState::Opened, $latencyMs, null, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function markFailed(OpenCommand $command, string $failureReason, array $metadata = []): void
    {
        $this->finalize($command, OpenCommandState::Failed, null, $failureReason, $metadata);
    }

    /**
     * Late actuation confirmation (v1.2): a Traccar telemetry record showing the output fired upgrades a
     * provisional `dispatched` command to the confirmed `opened` terminal (R-GSM-03). No-op otherwise.
     */
    public function confirmActuation(OpenCommand $command): void
    {
        if ($command->state !== OpenCommandState::Dispatched) {
            return;
        }

        $command->forceFill([
            'state' => OpenCommandState::Opened->value,
            'completed_at' => $this->clock->now(),
        ])->save();
    }

    public function markExpired(OpenCommand $command): void
    {
        if ($command->state->isTerminal()) {
            return;
        }

        $command->forceFill([
            'state' => OpenCommandState::Expired->value,
            'completed_at' => $this->clock->now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordAttempt(
        OpenCommand $command,
        int $attemptNo,
        DriverType $driver,
        OpenCommandState $state,
        ?string $failureReason,
        ?int $latencyMs,
        array $metadata = [],
        ?string $voiceGatewayId = null,
    ): OpenCommandAttempt {
        $now = $this->clock->now();

        return OpenCommandAttempt::query()->create([
            'open_command_id' => $command->getKey(),
            'attempt_no' => $attemptNo,
            'driver' => $driver->value,
            'voice_gateway_id' => $voiceGatewayId,
            'state' => $state->value,
            'failure_reason' => $failureReason,
            'started_at' => $now,
            'completed_at' => $state->isTerminal() ? $now : null,
            'latency_ms' => $latencyMs,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function finalize(OpenCommand $command, OpenCommandState $state, ?int $latencyMs, ?string $failureReason, array $metadata = []): void
    {
        if ($command->state->isTerminal()) {
            return;
        }

        $now = $this->clock->now();
        $latency = $latencyMs ?? $now->getTimestamp() * 1000 + (int) $now->format('v') - ($command->requested_at->getTimestamp() * 1000 + (int) $command->requested_at->format('v'));

        $merged = $metadata === [] ? $command->metadata : array_merge((array) $command->metadata, $metadata);

        $command->forceFill([
            'state' => $state->value,
            'failure_reason' => $failureReason,
            'dispatched_at' => $state->isSuccess() ? $now : $command->dispatched_at,
            'completed_at' => $now,
            'latency_ms' => $state->isSuccess() ? max(0, $latency) : null,
            'metadata' => $merged === [] ? null : $merged,
        ])->save();
    }
}
