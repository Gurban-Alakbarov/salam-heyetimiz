<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Models\OpenCommand;

/**
 * Server-computed `expected_completion_ms` (R-GSM-09 / HIGH-14): the rolling p90 of recent successful
 * opens for the device+driver. Falls back to a per-driver constant when there are < 10 samples. A
 * fixed constant is never returned blindly — the rolling estimate is preferred whenever data exists.
 */
final class ExpectedCompletionEstimator
{
    private const MIN_SAMPLES = 10;

    public function estimate(Device $device, DriverType $driver): int
    {
        $window = (int) config('domain.device_comm.expected_completion_rolling_window', 100);

        $latencies = OpenCommand::query()
            ->where('device_id', $device->getKey())
            ->where('driver', $driver->value)
            ->whereIn('state', ['dispatched', 'opened'])
            ->whereNotNull('latency_ms')
            ->orderByDesc('id')
            ->limit($window)
            ->pluck('latency_ms')
            ->map(static fn ($ms): int => (int) $ms)
            ->all();

        $estimate = count($latencies) < self::MIN_SAMPLES
            ? (int) config('domain.device_comm.expected_completion_ms_defaults.'.$driver->value, 5000)
            : $this->p90($latencies);

        return max($estimate, $this->confirmationWindowMs($device, $driver));
    }

    /**
     * Y2 — a model confirmed by polling the device's own commandResult (VL110C: the open reply comes ~15s later)
     * settles only after up to the driver's whole poll window. Clients size their polling on this estimate
     * (app: ceil(ms/1200)+3 polls, home widget: +2), so it must cover that window plus the send — otherwise a
     * successful open is shown as a timeout. 0 for every other model (their estimate is unchanged).
     */
    private function confirmationWindowMs(Device $device, DriverType $driver): int
    {
        if ($driver !== DriverType::Traccar || ! (bool) ($device->model?->command_result_confirm ?? false)) {
            return 0;
        }

        $attempts = max(1, (int) config('domain.device_comm.command_result_poll_attempts', 15));
        $intervalMs = max(0, (int) config('domain.device_comm.command_result_poll_interval_ms', 1500));

        return $attempts * $intervalMs + (int) config('domain.device_comm.command_result_send_overhead_ms', 3000);
    }

    /**
     * @param  array<int, int>  $values
     */
    private function p90(array $values): int
    {
        sort($values);
        $index = (int) ceil(0.9 * count($values)) - 1;
        $index = max(0, min($index, count($values) - 1));

        return $values[$index];
    }
}
