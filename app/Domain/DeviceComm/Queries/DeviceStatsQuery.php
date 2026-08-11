<?php

namespace App\Domain\DeviceComm\Queries;

use App\Domain\DeviceComm\Models\OpenCommand;
use App\Support\Time\Clock;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Aggregated open statistics for a device over a window (getDeviceStats; DeviceAdminDetail.stats).
 * Computed live against open_commands here; at scale these reads move to device_daily_stats (DB Arch
 * §10) — out of scope for the core.
 */
final class DeviceStatsQuery
{
    private const PERIOD_DAYS = ['7d' => 7, '30d' => 30, '90d' => 90];

    public function __construct(private readonly Clock $clock) {}

    /**
     * @return array{period: string, opens_total: int, opens_success: int, opens_failed: int, success_rate: float, avg_latency_ms: ?int, last_open_at: ?string}
     */
    public function forDevice(int $deviceId, string $period): array
    {
        $days = self::PERIOD_DAYS[$period] ?? 30;
        $since = $this->clock->now()->subDays($days);

        $base = fn (): Builder => OpenCommand::query()
            ->where('device_id', $deviceId)
            ->where('requested_at', '>=', $since);

        $total = (clone $base())->count();
        $success = (clone $base())->whereIn('state', ['dispatched', 'opened'])->count();
        $failed = (clone $base())->whereIn('state', ['failed', 'expired'])->count();
        $avgLatency = (clone $base())->whereNotNull('latency_ms')->avg('latency_ms');
        $lastOpenAt = (clone $base())->whereIn('state', ['dispatched', 'opened'])->max('completed_at');

        return [
            'period' => array_key_exists($period, self::PERIOD_DAYS) ? $period : '30d',
            'opens_total' => $total,
            'opens_success' => $success,
            'opens_failed' => $failed,
            'success_rate' => $total > 0 ? round($success / $total, 4) : 0.0,
            'avg_latency_ms' => $avgLatency !== null ? (int) round((float) $avgLatency) : null,
            'last_open_at' => $lastOpenAt !== null ? \Illuminate\Support\Carbon::parse($lastOpenAt)->toIso8601String() : null,
        ];
    }
}
