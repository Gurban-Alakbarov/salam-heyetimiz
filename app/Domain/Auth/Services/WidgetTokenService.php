<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Auth\Models\WidgetToken;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Home-screen widget credential. The widget opens a door from a background isolate long after the app's
 * 15-minute access token expired, and it must NOT refresh the session (a background rotation racing the app's
 * would look like refresh-token theft and log the user out). So the app asks for a separate token that:
 *  - is opaque (`wgt_…`), SHA-256 hashed at rest, bound to the install (user_devices row);
 *  - is accepted ONLY by the open and command-status endpoints (guard `widget`, routes `auth:user,widget`);
 *  - lives domain.auth.widget.ttl_days (90) and is re-issued by the app while it is used, so an active user's
 *    widget never expires; one active token per install — issuing revokes the previous one;
 *  - is revoked on logout of that install, account deletion and refresh-token reuse (theft); a blocked or
 *    deleted account is refused at use time.
 */
final class WidgetTokenService
{
    public const PREFIX = 'wgt_';

    public function __construct(private readonly Clock $clock) {}

    /** @return array{token: string, model: WidgetToken} */
    public function issue(User $user, UserDevice $device): array
    {
        $plaintext = self::PREFIX.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->clock->now();

        $model = DB::transaction(function () use ($user, $device, $plaintext, $now): WidgetToken {
            $this->revokeForDevice((int) $user->getKey(), (int) $device->getKey(), 'reissued');

            return WidgetToken::query()->create([
                'user_id' => $user->getKey(),
                'user_device_id' => $device->getKey(),
                'token_hash' => hash('sha256', $plaintext),
                'issued_at' => $now,
                'expires_at' => $now->copy()->addDays((int) config('domain.auth.widget.ttl_days', 90)),
            ]);
        });

        return ['token' => $plaintext, 'model' => $model];
    }

    /** The live token for a presented plaintext, or null (unknown / revoked / expired / not a widget token). */
    public function resolve(string $plaintext): ?WidgetToken
    {
        if (! str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        /** @var WidgetToken|null $token */
        $token = WidgetToken::query()->where('token_hash', hash('sha256', $plaintext))->first();
        $now = $this->clock->now();
        if ($token === null || $token->revoked_at !== null || $token->expires_at->lessThanOrEqualTo($now)) {
            return null;
        }

        // Record use at most once an hour — no write per tap.
        if ($token->last_used_at === null || $token->last_used_at->lessThan($now->copy()->subHour())) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        return $token;
    }

    public function revokeForDevice(int $userId, int $userDeviceId, string $reason): int
    {
        return WidgetToken::query()->where('user_id', $userId)->where('user_device_id', $userDeviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $this->clock->now(), 'revocation_reason' => $reason]);
    }

    public function revokeForUser(int $userId, string $reason): int
    {
        return WidgetToken::query()->where('user_id', $userId)->whereNull('revoked_at')
            ->update(['revoked_at' => $this->clock->now(), 'revocation_reason' => $reason]);
    }
}
