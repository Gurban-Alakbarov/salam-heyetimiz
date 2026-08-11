<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\SettingsConfigBridge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies DB-backed runtime settings onto config for the current request (R-DOM-09). Fail-open: any error
 * (e.g. settings table absent during migrate) is swallowed so the request still proceeds on file defaults.
 */
final class ApplyRuntimeSettings
{
    public function __construct(
        private readonly SettingsConfigBridge $bridge,
        private readonly SettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->bridge->apply($this->settings);
        } catch (\Throwable) {
            // fail-open: keep config-file defaults
        }

        return $next($request);
    }
}
