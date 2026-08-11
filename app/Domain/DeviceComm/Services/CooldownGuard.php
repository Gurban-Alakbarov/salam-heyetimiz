<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\DeviceComm\Exceptions\CooldownException;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\Cache;

/**
 * Open cooldowns (R-DOM-05/09): per-(user, device) 5 s and per-device 2 s, held in the cache
 * (Redis SETNX in production). The hard guard is the rate limiter (12/user, 4/device — R-SEC-16);
 * this prevents accidental double-taps and surfaces a Retry-After hint.
 */
final class CooldownGuard
{
    public function __construct(private readonly Clock $clock) {}

    /** Throw if a cooldown is active; otherwise reserve both windows. */
    public function enforce(int $userId, int $deviceId): void
    {
        $now = $this->clock->now()->getTimestamp();

        $udTtl = (int) config('domain.devices.cooldowns.user_device_seconds', 5);
        $devTtl = (int) config('domain.devices.cooldowns.device_global_seconds', 2);

        $this->assertFree("open-cd:ud:{$userId}:{$deviceId}", $now);
        $this->assertFree("open-cd:dev:{$deviceId}", $now);

        Cache::put("open-cd:ud:{$userId}:{$deviceId}", $now + $udTtl, $udTtl);
        Cache::put("open-cd:dev:{$deviceId}", $now + $devTtl, $devTtl);
    }

    private function assertFree(string $key, int $now): void
    {
        $releaseAt = Cache::get($key);

        if (is_int($releaseAt) && $releaseAt > $now) {
            throw new CooldownException(max(1, $releaseAt - $now));
        }
    }
}
