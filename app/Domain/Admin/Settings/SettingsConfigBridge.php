<?php

namespace App\Domain\Admin\Settings;

use App\Domain\Admin\Services\SettingsService;

/**
 * Overlays runtime DB settings onto Laravel's config so the existing services (JwtService, OtpService,
 * AdminAuthService) pick up admin-edited values with NO change to those services — they keep reading the
 * same config('domain.auth.*') keys, which we rewrite per-request. This is what makes the Security/OTP
 * tunables genuinely live (not just stored). Only positive ints overlay; a missing/zero value leaves the
 * config-file default untouched.
 */
final class SettingsConfigBridge
{
    /** catalog (group,key) → laravel config key */
    private const MAP = [
        'domain.auth.access.user.ttl_seconds' => ['security', 'jwt_user_ttl_seconds'],
        'domain.auth.access.admin.ttl_seconds' => ['security', 'jwt_admin_ttl_seconds'],
        'domain.auth.refresh.ttl_days' => ['security', 'refresh_ttl_days'],
        'domain.auth.admin_login.max_failed' => ['security', 'max_login_attempts'],
        'domain.auth.admin_login.lockout_minutes' => ['security', 'lockout_minutes'],
        'domain.auth.otp.ttl_seconds' => ['otp', 'otp_ttl_seconds'],
        'domain.auth.otp.length' => ['otp', 'otp_length'],
        'domain.auth.otp.max_attempts' => ['otp', 'otp_max_attempts'],
        'domain.auth.otp.resend_seconds' => ['otp', 'otp_resend_seconds'],
    ];

    public function apply(SettingsService $settings): void
    {
        $overlay = [];
        foreach (self::MAP as $configKey => [$group, $key]) {
            $value = $settings->value($group, $key);
            if (is_int($value) && $value > 0) {
                $overlay[$configKey] = $value;
            }
        }
        if ($overlay !== []) {
            config($overlay);
        }
    }
}
