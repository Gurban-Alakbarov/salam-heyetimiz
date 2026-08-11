<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\AdminAuthService;

/** Step 1 of admin login — email + password → 2FA challenge (openapi adminLogin). */
final class AdminLogin
{
    public function __construct(private readonly AdminAuthService $admins) {}

    /**
     * @return array{challenge_token: string, expires_in_seconds: int, requires_totp: bool}
     */
    public function handle(string $email, string $password, string $ip, ?string $userAgent): array
    {
        return $this->admins->login($email, $password, $ip, $userAgent);
    }
}
