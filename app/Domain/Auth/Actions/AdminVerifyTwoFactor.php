<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\AdminAuthService;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Support\IssuedAccessToken;

/** Step 2 of admin login — TOTP or recovery code → admin JWT (openapi adminVerify2fa). */
final class AdminVerifyTwoFactor
{
    public function __construct(private readonly AdminAuthService $admins) {}

    /**
     * @return array{admin: AdminUser, token: IssuedAccessToken, used_recovery_code: bool, recovery_codes_remaining: int}
     */
    public function handle(string $challengeToken, ?string $totp, ?string $recoveryCode, string $ip, ?string $userAgent): array
    {
        return $this->admins->verifyTwoFactor($challengeToken, $totp, $recoveryCode, $ip, $userAgent);
    }
}
