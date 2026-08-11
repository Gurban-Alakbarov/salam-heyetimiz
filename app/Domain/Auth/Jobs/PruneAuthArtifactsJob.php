<?php

namespace App\Domain\Auth\Jobs;

use App\Domain\Auth\Models\AuthAttempt;
use App\Domain\Auth\Models\Otp;
use App\Domain\Auth\Models\RefreshToken;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retention sweep for auth artifacts (Tech Spec §15.6): drop consumed/expired OTPs, long-revoked
 * refresh tokens, and aged auth_attempts. Keeps the tables small and bounds PII retention.
 */
class PruneAuthArtifactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * @return array{otps: int, refresh_tokens: int, auth_attempts: int}
     */
    public function handle(Clock $clock): array
    {
        $now = $clock->now();

        $otps = Otp::query()
            ->where(fn ($q) => $q->whereNotNull('consumed_at')->orWhere('expires_at', '<', $now))
            ->delete();

        $refreshTokens = RefreshToken::query()
            ->whereNotNull('revoked_at')
            ->where('revoked_at', '<', $now->subDays((int) config('domain.auth.retention.revoked_refresh_tokens_days')))
            ->delete();

        $authAttempts = AuthAttempt::query()
            ->where('created_at', '<', $now->subDays((int) config('domain.auth.retention.auth_attempts_days')))
            ->delete();

        return [
            'otps' => (int) $otps,
            'refresh_tokens' => (int) $refreshTokens,
            'auth_attempts' => (int) $authAttempts,
        ];
    }
}
