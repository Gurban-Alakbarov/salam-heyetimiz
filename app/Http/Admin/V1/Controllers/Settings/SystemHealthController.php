<?php

namespace App\Http\Admin\V1\Controllers\Settings;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Services\SettingsService;
use App\Http\Concerns\AuthorizesAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

/** Live system health for the Settings → System tab (system.settings.manage). All checks are real. */
class SystemHealthController
{
    use AuthorizesAdmin;

    public function __construct(private readonly SettingsService $settings) {}

    /** GET /admin/v1/system/health — adminSystemHealth. */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::SYSTEM_SETTINGS_MANAGE);

        return response()->json([
            'app' => ['laravel' => app()->version(), 'php' => PHP_VERSION, 'env' => app()->environment()],
            'database' => $this->ping(fn () => DB::select('select 1')),
            'redis' => $this->ping(fn () => Redis::connection()->ping()),
            'queue' => [
                'connection' => (string) config('queue.default'),
                'failed' => $this->safe(fn (): int => (int) DB::table('failed_jobs')->count(), 0),
            ],
            'horizon' => ['installed' => class_exists(\Laravel\Horizon\Horizon::class)],
            'disk' => $this->disk(),
            'memory' => [
                'php_used_mb' => round(memory_get_usage(true) / 1048576, 1),
                'php_limit' => (string) ini_get('memory_limit'),
            ],
            'cpu' => ['load_1m' => $this->loadAvg()],
            'smtp' => ['configured' => (string) $this->settings->value('email', 'smtp_host') !== ''],
            'payment' => [
                'enabled' => (bool) $this->settings->value('payments', 'kapital_enabled'),
                'configured' => (string) $this->settings->value('payments', 'kapital_merchant_id') !== '',
            ],
            'cloudflare' => ['proxied' => $request->hasHeader('CF-RAY'), 'ray' => $request->header('CF-RAY')],
            'traccar' => $this->traccar(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function traccar(): array
    {
        $base = (string) $this->settings->value('traccar', 'api_url');
        $token = (string) $this->settings->value('traccar', 'api_token');
        $deviceCount = $this->safe(fn (): int => (int) DB::table('traccar_devices')->count(), 0);
        $queueSize = $this->safe(fn (): int => (int) DB::table('whitelist_changes')->where('status', 'pending')->count(), 0);
        $lastWebhook = $this->safe(fn (): ?string => DB::table('device_diagnostics')->max('reported_at'), null);

        $status = 'offline';
        $version = null;
        if ($base !== '') {
            try {
                $req = Http::timeout(6);
                if ($token !== '') {
                    $req = $req->withToken($token);
                }
                $res = $req->get(rtrim($base, '/').'/api/server');
                if ($res->successful()) {
                    $status = 'connected';
                    $version = $res->json('version');
                }
            } catch (\Throwable) {
                $status = 'offline';
            }
        }

        return [
            'status' => $status,
            'version' => $version,
            'device_count' => $deviceCount,
            'queue_size' => $queueSize,
            'last_webhook' => $lastWebhook,
        ];
    }

    /**
     * @return array{ok: bool}
     */
    private function ping(callable $check): array
    {
        try {
            $check();

            return ['ok' => true];
        } catch (\Throwable) {
            return ['ok' => false];
        }
    }

    private function safe(callable $fn, mixed $default): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function disk(): array
    {
        $total = @disk_total_space(base_path()) ?: 0;
        $free = @disk_free_space(base_path()) ?: 0;

        return [
            'total_gb' => round($total / 1073741824, 1),
            'free_gb' => round($free / 1073741824, 1),
            'used_percent' => $total > 0 ? round((($total - $free) / $total) * 100, 1) : 0,
        ];
    }

    private function loadAvg(): ?float
    {
        return function_exists('sys_getloadavg') ? round((sys_getloadavg()[0] ?? 0), 2) : null;
    }
}
