<?php

namespace App\Http\Api\V1\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Liveness / readiness probes (OpenAPI getHealthLive / getHealthReady; Tech Spec
 * §3.6). Public, no auth. Readiness checks DB + Redis and may return 503.
 */
class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(static fn () => DB::connection()->getPdo() !== null),
            'redis' => $this->check(static fn () => Redis::connection()->ping() !== null),
        ];

        $ok = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ok ? 'ready' : 'degraded',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (\Throwable) {
            return false;
        }
    }
}
