<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\DeviceFingerprintData;
use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Database\Eloquent\Collection;

/**
 * Device registration (Tech Spec §15.2): one user_devices row per (user, install_uuid).
 * Logging in from an install creates or refreshes the row, updates the push token, and
 * un-revokes a previously revoked install. Push tokens are hidden/secret (R-SEC-15).
 */
final class UserDeviceService
{
    public function __construct(private readonly Clock $clock) {}

    public function registerOrUpdate(User $user, DeviceFingerprintData $device, string $ip): UserDevice
    {
        $now = $this->clock->now();

        /** @var UserDevice $row */
        $row = UserDevice::query()->firstOrNew([
            'user_id' => $user->getKey(),
            'install_uuid' => $device->installUuid,
        ]);

        $row->forceFill([
            'platform' => $device->platform,
            'os_version' => $device->osVersion,
            'app_version' => $device->appVersion,
            'device_model' => $device->deviceModel,
            'last_seen_at' => $now,
            'last_seen_ip' => $ip,
            'revoked_at' => null, // re-login re-activates the install
        ]);

        if ($device->pushToken !== null && $device->pushToken !== $row->getRawOriginal('push_token')) {
            $row->forceFill([
                'push_token' => $device->pushToken,
                'push_token_updated_at' => $now,
                'push_invalid' => false,
            ]);
        }

        $row->save();

        return $row;
    }

    /** Resolve the install row for a (user, fingerprint) pair, e.g. on logout/biometrics. */
    public function findByFingerprint(int $userId, ?string $installUuid): ?UserDevice
    {
        if ($installUuid === null || $installUuid === '') {
            return null;
        }

        return UserDevice::query()
            ->where('user_id', $userId)
            ->where('install_uuid', $installUuid)
            ->first();
    }

    /**
     * Active push-delivery targets for a user: registered, un-revoked installs carrying a live token
     * that FCM has not rejected (R-SEC-15). The notifications push fan-out reads tokens through here
     * rather than reaching into user_devices directly (R-ARCH-06).
     *
     * @return Collection<int, UserDevice>
     */
    public function activePushTargets(int $userId): Collection
    {
        return UserDevice::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('push_invalid', false)
            ->whereNotNull('push_token')
            ->get();
    }

    /** Soft-invalidate a token FCM reported as unregistered; a later login/token refresh re-activates it. */
    public function markPushInvalid(UserDevice $device): void
    {
        $device->forceFill(['push_invalid' => true])->save();
    }

    /**
     * Register/refresh the FCM push token for a single install (openapi upsertPushToken). The install
     * is resolved from the JWT fingerprint; ONLY that user_devices row is touched — other installs keep
     * their own tokens (multi-device). Setting a fresh token clears any prior `push_invalid` flag
     * (re-activation). A fingerprint that matches no install is a no-op — no new install row is created
     * (identity is minted only at login, R-SEC-15).
     */
    public function upsertPushToken(User $user, ?string $fingerprint, string $token): void
    {
        $device = $this->findByFingerprint((int) $user->getKey(), $fingerprint);

        $device?->forceFill([
            'push_token' => $token,
            'push_token_updated_at' => $this->clock->now(),
            'push_invalid' => false,
        ])->save();
    }

    /**
     * De-register the push token for a single install (openapi deletePushToken). Resolves the install
     * from the JWT fingerprint and nulls ONLY that row's token — other installs are untouched. No-op
     * when the fingerprint matches no install; the caller's refresh/access session is unaffected.
     */
    public function clearPushToken(User $user, ?string $fingerprint): void
    {
        $device = $this->findByFingerprint((int) $user->getKey(), $fingerprint);

        $device?->forceFill([
            'push_token' => null,
            'push_token_updated_at' => $this->clock->now(),
        ])->save();
    }
}
