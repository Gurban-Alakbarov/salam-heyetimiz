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

        if (count($latencies) < self::MIN_SAMPLES) {
            return (int) config('domain.device_comm.expected_completion_ms_defaults.'.$driver->value, 5000);
        }

        return $this->p90($latencies);
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
