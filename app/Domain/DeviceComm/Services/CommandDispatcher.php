<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\DTOs\DriverOpenResult;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\DeviceComm\Models\OpenCommand;

/**
 * Drives a queued open command through dispatch (R-GSM-04/06). Resolves the device's driver, records
 * a primary attempt, and — on a transient failure — retries once via the model's fallback driver. The
 * parent command's terminal state is the last attempt's outcome. With no concrete driver registered
 * (the GSM batch binds them), dispatch terminates as `failed/driver_unsupported` — real behaviour.
 */
final class CommandDispatcher
{
    public function __construct(
        private readonly OpenCommandService $commands,
        private readonly DriverResolver $drivers,
    ) {}

    public function dispatch(OpenCommand $command): void
    {
        if ($command->state->isTerminal()) {
            return; // idempotent — already resolved (e.g. expired by the sweep)
        }

        $device = Device::query()->find($command->device_id);
        if ($device === null) {
            $this->commands->markFailed($command, 'device_not_found');
            OpenCommandCompleted::dispatch($command->refresh());

            return;
        }

        $this->commands->markDispatching($command);

        $primaryType = $command->driver; // snapshot enum
        $result = $this->attempt($command, $device, $primaryType, 1);

        // Fallback once on a transient failure (R-GSM-04).
        if ($result !== null && ! $result->dispatched && $this->isTransient($result->failureReason)) {
            $fallbackType = $this->fallbackDriver($device);
            if ($fallbackType !== null && $fallbackType !== $primaryType) {
                $fallback = $this->attempt($command, $device, $fallbackType, 2);
                if ($fallback !== null) {
                    $result = $fallback;
                    $primaryType = $fallbackType;
                }
            }
        }

        $this->finalize($command, $primaryType, $result);
        OpenCommandCompleted::dispatch($command->refresh());
    }

    /** Run a single attempt; returns the driver result, or null when no driver is registered. */
    private function attempt(OpenCommand $command, Device $device, DriverType $type, int $attemptNo): ?DriverOpenResult
    {
        $driver = $this->drivers->resolveType($type);

        if ($driver === null) {
            $this->commands->recordAttempt($command, $attemptNo, $type, OpenCommandState::Failed, 'driver_unsupported', null);

            return null;
        }

        $this->commands->incrementAttempts($command);
        $result = $driver->open($device, $command);

        $state = $result->dispatched
            ? ($result->actuated ? OpenCommandState::Opened : OpenCommandState::Dispatched)
            : OpenCommandState::Failed;

        $this->commands->recordAttempt($command, $attemptNo, $type, $state, $result->failureReason, $result->latencyMs, $result->metadata);

        return $result;
    }

    private function finalize(OpenCommand $command, DriverType $type, ?DriverOpenResult $result): void
    {
        // No driver registered for any attempt → unsupported configuration.
        if ($result === null) {
            $this->commands->markFailed($command, 'driver_unsupported');

            return;
        }

        if (! $result->dispatched) {
            $this->commands->markFailed($command, $result->failureReason ?? 'dispatch_failed', $result->metadata);

            return;
        }

        // Confirmed only when both the driver observed actuation AND the type may confirm (R-GSM-03).
        if ($result->actuated && $type->confirmsActuation()) {
            $this->commands->markOpened($command, $result->latencyMs, $result->metadata);

            return;
        }

        $this->commands->markDispatched($command, $result->latencyMs, $result->metadata);
    }

    private function fallbackDriver(Device $device): ?DriverType
    {
        // Builder::value() applies the model cast, so this is already a DriverType (or null).
        $fallback = $device->model()->value('fallback_open_driver');

        if ($fallback instanceof DriverType) {
            return $fallback;
        }

        return is_string($fallback) ? DriverType::from($fallback) : null;
    }

    private function isTransient(?string $failureReason): bool
    {
        if ($failureReason === null) {
            return false;
        }

        return in_array($failureReason, (array) config('domain.device_comm.transient_failure_codes', []), true);
    }
}
