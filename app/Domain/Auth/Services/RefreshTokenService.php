<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\RefreshTokenRevocationReason;
use App\Domain\Auth\Events\RefreshTokenReuseDetected;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Opaque refresh tokens (R-SEC-02): 60-day, SHA-256 hashed at rest, device-fingerprint
 * bound, and rotated on every use. Presenting an already-rotated token is treated as a
 * theft signal — the whole install's token family is revoked and a security event fires.
 */
final class RefreshTokenService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Mint a new refresh token for an install. Returns the plaintext (returned to the
     * client once) plus the persisted row.
     *
     * @return array{plaintext: string, model: RefreshToken}
     */
    public function issue(User $user, UserDevice $device, string $ip, ?string $userAgent): array
    {
        $plaintext = $this->newPlaintext();
        $now = $this->clock->now();

        $model = RefreshToken::query()->create([
            'user_id' => $user->getKey(),
            'user_device_id' => $device->getKey(),
            'token_hash' => $this->hash($plaintext),
            'issued_at' => $now,
            'expires_at' => $now->addDays((int) config('domain.auth.refresh.ttl_days')),
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        return ['plaintext' => $plaintext, 'model' => $model];
    }

    /**
     * Rotate a presented refresh token. Returns the owning user/device and the freshly
     * minted token, or throws (handled by the caller as 401).
     *
     * @return array{user: User, device: UserDevice, plaintext: string, model: RefreshToken}
     */
    public function rotate(string $plaintext, string $ip, ?string $userAgent): array
    {
        $now = $this->clock->now();

        // The transaction RETURNS an outcome; the security revoke + 401 happen afterwards so the
        // expired/rotated state and the family revocation persist (a throw here would roll them back).
        $outcome = DB::transaction(function () use ($plaintext, $ip, $userAgent, $now): array {
            /** @var RefreshToken|null $current */
            $current = RefreshToken::query()
                ->where('token_hash', $this->hash($plaintext))
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                return ['status' => 'invalid'];
            }

            // Replay of an already-revoked token → theft signal (handled outside the txn).
            if ($current->revoked_at !== null) {
                return [
                    'status' => 'reuse',
                    'user_id' => (int) $current->user_id,
                    'user_device_id' => (int) $current->user_device_id,
                ];
            }

            if ($current->expires_at->lessThanOrEqualTo($now)) {
                $current->forceFill([
                    'revoked_at' => $now,
                    'revocation_reason' => RefreshTokenRevocationReason::Expired->value,
                ])->save();

                return ['status' => 'invalid'];
            }

            /** @var User $user */
            $user = $current->user()->firstOrFail();
            /** @var UserDevice $device */
            $device = $current->userDevice()->firstOrFail();

            $issued = $this->issue($user, $device, $ip, $userAgent);

            $current->forceFill([
                'revoked_at' => $now,
                'revocation_reason' => RefreshTokenRevocationReason::Rotated->value,
                'last_used_at' => $now,
                'replaced_by_id' => $issued['model']->getKey(),
            ])->save();

            return [
                'status' => 'ok',
                'user' => $user,
                'device' => $device,
                'plaintext' => $issued['plaintext'],
                'model' => $issued['model'],
            ];
        });

        if ($outcome['status'] === 'reuse') {
            $this->revokeActiveForDevice($outcome['user_id'], $outcome['user_device_id'], RefreshTokenRevocationReason::Security);
            RefreshTokenReuseDetected::dispatch($outcome['user_id'], $outcome['user_device_id']);

            throw new \App\Domain\Auth\Exceptions\InvalidRefreshTokenException();
        }

        if ($outcome['status'] !== 'ok') {
            throw new \App\Domain\Auth\Exceptions\InvalidRefreshTokenException();
        }

        return [
            'user' => $outcome['user'],
            'device' => $outcome['device'],
            'plaintext' => $outcome['plaintext'],
            'model' => $outcome['model'],
        ];
    }

    /** Revoke the active refresh token(s) for an install (e.g. on logout). */
    public function revokeActiveForDevice(int $userId, int $userDeviceId, RefreshTokenRevocationReason $reason): int
    {
        return RefreshToken::query()
            ->where('user_id', $userId)
            ->where('user_device_id', $userDeviceId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $this->clock->now(),
                'revocation_reason' => $reason->value,
            ]);
    }

    private function newPlaintext(): string
    {
        $bytes = (int) config('domain.auth.refresh.bytes');

        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    private function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
