<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate-limit buckets per Tech Spec §9.5 / OpenAPI. Routes attach these via
 * `throttle:<name>` as endpoints are implemented in later increments.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // OTP request: 3/phone/10min AND 30/IP/hour (R-SEC-03 / R-SEC-16).
        RateLimiter::for('otp-request', function (Request $request) {
            $phone = (string) $request->input('phone');

            return [
                Limit::perMinutes(10, 3)->by('otp-req-phone:'.$phone),
                Limit::perHour(30)->by('otp-req-ip:'.$request->ip()),
            ];
        });

        // OTP verify: 10/phone/10min.
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 10)
            ->by('otp-verify-phone:'.(string) $request->input('phone')));

        // ---- Email-OTP registration + login limiters (unified 429 envelope) ----
        $tooMany = fn () => response()->json([
            'success' => false,
            'message' => 'Çox sayda sorğu göndərildi. Bir az sonra yenidən cəhd edin.',
            'data' => null, 'meta' => null, 'errors' => ['code' => 'rate_limited'],
        ], 429);
        $email = fn (Request $request) => mb_strtolower((string) $request->input('email'));

        // Register: 5/email/hour AND 20/IP/hour.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perHour(5)->by('register-email:'.$email($request))->response($tooMany),
            Limit::perHour(20)->by('register-ip:'.$request->ip())->response($tooMany),
        ]);

        // Email login: 5/email/10min AND 30/IP/hour.
        RateLimiter::for('email-login', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('login-email:'.$email($request))->response($tooMany),
            Limit::perHour(30)->by('login-ip:'.$request->ip())->response($tooMany),
        ]);

        // Verify email OTP: 10/email/10min.
        RateLimiter::for('otp-verify-email', fn (Request $request) => Limit::perMinutes(10, 10)
            ->by('verify-email:'.$email($request))->response($tooMany));

        // Resend email OTP: 3/email/10min.
        RateLimiter::for('otp-resend', fn (Request $request) => Limit::perMinutes(10, 3)
            ->by('resend-email:'.$email($request))->response($tooMany));

        // Open: 12/user/min AND 4/device/min (R-SEC-16). Cooldowns are separate (Redis SETNX).
        RateLimiter::for('open', function (Request $request) {
            $userId = optional($request->user())->getAuthIdentifier() ?? $request->ip();
            $deviceId = (string) $request->route('deviceId');

            return [
                Limit::perMinute(12)->by('open-user:'.$userId),
                Limit::perMinute(4)->by('open-device:'.$deviceId),
            ];
        });

        // Visitor open (public, token-authenticated): 6/token/min AND 20/IP/min. The token is hashed into
        // the bucket key so raw tokens never land in the cache store.
        RateLimiter::for('visitor-open', function (Request $request) {
            $token = (string) $request->route('token');

            return [
                Limit::perMinute(6)->by('visitor-open-token:'.sha1($token)),
                Limit::perMinute(20)->by('visitor-open-ip:'.$request->ip()),
            ];
        });

        // Authenticated mobile generic: 120/min/user.
        RateLimiter::for('mobile', fn (Request $request) => Limit::perMinute(120)
            ->by(optional($request->user())->getAuthIdentifier() ?? $request->ip()));

        // Admin generic: 600/min/admin.
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(600)
            ->by(optional($request->user())->getAuthIdentifier() ?? $request->ip()));

        // Unauthenticated generic: 30/min/IP.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
