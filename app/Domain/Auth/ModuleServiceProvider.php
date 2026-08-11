<?php

namespace App\Domain\Auth;

use App\Domain\Auth\Events\AdminAuthenticated;
use App\Domain\Auth\Events\UserAuthenticated;
use App\Domain\Auth\Guards\JwtRequestGuard;
use App\Domain\Auth\Listeners\RecordAdminLogin;
use App\Domain\Auth\Listeners\RecordSuccessfulUserLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Identity & Auth (Backend Arch §14.1): OTP, RS256 JWT issuance, refresh-token rotation,
 * biometric enrollment, admin TOTP + recovery codes, auth-attempt logging. Registers the
 * two disjoint JWT guards (R-API-03) and the post-login bookkeeping listeners.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Disjoint mobile/admin JWT guards. viaRequest keeps actingAs() working in tests.
        Auth::viaRequest('salam-jwt-user', static fn (Request $request) => app(JwtRequestGuard::class)->resolveUser($request));
        Auth::viaRequest('salam-jwt-admin', static fn (Request $request) => app(JwtRequestGuard::class)->resolveAdmin($request));

        Event::listen(UserAuthenticated::class, RecordSuccessfulUserLogin::class);
        Event::listen(AdminAuthenticated::class, RecordAdminLogin::class);
    }
}
