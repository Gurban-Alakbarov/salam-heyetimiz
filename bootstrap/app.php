<?php

use App\Http\Middleware\EnforceJson;
use App\Http\Middleware\LocaleNegotiator;
use App\Http\Middleware\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Application Bootstrap (Laravel 12)
|--------------------------------------------------------------------------
| Surfaces are kept disjoint per PROJECT_CONSTITUTION R-API-03:
|   - mobile / public  -> /v1            (routes/api.php,      group "api")
|   - admin            -> /admin/v1      (routes/admin.php,    group "api")
|   - webhooks         -> /v1/...        (routes/webhooks.php, group "webhook")
|
| Note (Laravel-12 skeleton mapping): Laravel 12 has no app/Http/Kernel.php or
| app/Console/Kernel.php. The middleware stack documented in
| BACKEND_ARCHITECTURE.md §6.5 is registered here; console scheduling lives in
| routes/console.php. This is the framework-version mapping of the documented
| intent, not an architecture change — see docs/decisions/laravel12-skeleton-mapping.md.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            Route::middleware('api')
                ->prefix('admin/v1')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('webhook')
                ->prefix('v1')
                ->group(base_path('routes/webhooks.php'));

            // API documentation surface (Swagger UI / ReDoc / OpenAPI spec) — gated by docs.access.
            Route::prefix('api')->group(base_path('routes/docs.php'));

            // JWKS for admin JWT verification (CRIT-04 / R-SEC-08). Well-known URI lives at
            // the host root (RFC 8615), so it sits outside the /v1 and /admin/v1 prefixes.
            Route::middleware('api')
                ->get('.well-known/jwks.json', [\App\Http\Api\V1\Controllers\Auth\JwksController::class, 'show'])
                ->name('getJwks');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mobile/public + admin both run through the "api" group.
        $middleware->api(prepend: [
            RequestId::class,        // X-Request-Id (ULID) + log/Context correlation
        ]);
        $middleware->api(append: [
            LocaleNegotiator::class,                              // Accept-Language ∩ available, default az (R-LOC-08)
            \App\Http\Middleware\ApplyRuntimeSettings::class,    // overlay DB settings → config (R-DOM-09)
        ]);

        $middleware->alias([
            'enforce.json' => EnforceJson::class,
            'verify.birpay' => \App\Http\Middleware\VerifyBirPaySignature::class,
            'admin.tfa' => \App\Http\Middleware\RequireAdminTfaVerified::class,
            'docs.access' => \App\Http\Middleware\DocsAccess::class,
        ]);

        // Webhook stack. CaptureRawBody runs FIRST so HMAC verifiers hash the exact raw bytes
        // before any JSON parsing (CRIT-07 / R-PAY-03); per-route verify.kapital validates the
        // signature before the controller.
        $middleware->group('webhook', [
            \App\Http\Middleware\CaptureRawBody::class,
            RequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Domain exceptions render to the standard error envelope (R-API-05).
        $exceptions->render(function (\App\Exceptions\Contracts\DomainException $e, \Illuminate\Http\Request $request) {
            return app(\App\Exceptions\Renderers\ApiExceptionRenderer::class)->renderDomain($e, $request);
        });

        // JWT guard rejection → the standard `unauthenticated` envelope (R-API-05 / R-SEC-02).
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('v1/*', 'admin/*')) {
                return app(\App\Exceptions\Renderers\ApiExceptionRenderer::class)->renderUnauthenticated($request);
            }

            return null;
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson() || $request->is('v1/*', 'admin/*')) {
                return app(\App\Exceptions\Renderers\ApiExceptionRenderer::class)->renderValidation($e, $request);
            }

            return null;
        });
    })
    ->create();
