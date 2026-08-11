<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Services\AdminAuthService;

/** Re-auth with a fresh TOTP and regenerate the 8-code recovery set (openapi regenerateRecoveryCodes). */
final class RegenerateRecoveryCodes
{
    public function __construct(private readonly AdminAuthService $admins) {}

    /**
     * @return array{codes: array<int, string>, generated_at: \Carbon\CarbonImmutable}
     */
    public function handle(AdminUser $admin, string $totp): array
    {
        return $this->admins->regenerateRecoveryCodes($admin, $totp);
    }
}
