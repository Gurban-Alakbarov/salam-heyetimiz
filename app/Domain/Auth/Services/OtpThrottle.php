<?php

namespace App\Domain\Auth\Services;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Auth\Exceptions\OtpThrottleException;
use Illuminate\Support\Facades\Cache;

/**
 * Per-phone OTP request rate limiting driven by Settings → OTP (daily/hourly limit + temporary block).
 * All limits default to 0 = unlimited, so this is a no-op until an operator enables it. Counters live in the
 * cache (Redis in prod). Enforced at issuance time inside OtpService::issue.
 */
final class OtpThrottle
{
    public function __construct(private readonly SettingsService $settings) {}

    public function register(string $phone): void
    {
        $hourly = (int) $this->settings->value('otp', 'otp_hourly_limit');
        $daily = (int) $this->settings->value('otp', 'otp_daily_limit');
        $blockMinutes = (int) $this->settings->value('otp', 'otp_temp_block_minutes');

        // Nothing to enforce.
        if ($hourly <= 0 && $daily <= 0) {
            return;
        }

        $key = hash('sha256', $phone);

        if (Cache::get("otp:block:$key") !== null) {
            throw OtpThrottleException::blocked($blockMinutes > 0 ? $blockMinutes * 60 : 3600);
        }

        $hourCount = (int) Cache::get("otp:h:$key", 0);
        $dayCount = (int) Cache::get("otp:d:$key", 0);

        if (($hourly > 0 && $hourCount >= $hourly) || ($daily > 0 && $dayCount >= $daily)) {
            if ($blockMinutes > 0) {
                Cache::put("otp:block:$key", true, $blockMinutes * 60);
            }
            throw OtpThrottleException::rateLimited($blockMinutes > 0 ? $blockMinutes * 60 : 3600);
        }

        Cache::put("otp:h:$key", $hourCount + 1, 3600);
        Cache::put("otp:d:$key", $dayCount + 1, 86400);
    }
}
