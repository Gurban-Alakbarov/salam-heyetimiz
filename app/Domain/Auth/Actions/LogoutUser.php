<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\RefreshTokenRevocationReason;
use App\Domain\Auth\Events\UserLoggedOut;
use App\Domain\Auth\Services\RefreshTokenService;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Users\Models\User;

/**
 * Revoke the current install's refresh token(s) (openapi logout). The install is resolved
 * from the access token's `fp` (install_uuid) claim; revoking is idempotent.
 */
final class LogoutUser
{
    public function __construct(
        private readonly UserDeviceService $devices,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function handle(User $user, ?string $fingerprint): void
    {
        $device = $this->devices->findByFingerprint((int) $user->getKey(), $fingerprint);

        if ($device === null) {
            return; // nothing bound to this install — already effectively logged out
        }

        $this->refreshTokens->revokeActiveForDevice(
            (int) $user->getKey(),
            (int) $device->getKey(),
            RefreshTokenRevocationReason::Logout,
        );

        UserLoggedOut::dispatch((int) $user->getKey(), (int) $device->getKey());
    }
}
