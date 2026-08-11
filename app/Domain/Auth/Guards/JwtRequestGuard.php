<?php

namespace App\Domain\Auth\Guards;

use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Resolves the bearer JWT into an Authenticatable for the two disjoint guards (R-API-03).
 * Used by Auth::viaRequest, so testing's actingAs() (which sets the user directly) bypasses
 * it — only real requests run this. On success the validated claims are stashed on the
 * request (jwt_fp / jwt_admin_tfa) for downstream actions/middleware.
 */
final class JwtRequestGuard
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly SettingsService $settings,
    ) {}

    public function resolveUser(Request $request): ?Authenticatable
    {
        $bearer = $request->bearerToken();
        if ($bearer === null) {
            return null;
        }

        $claims = $this->jwt->parseUserClaims($bearer);
        if ($claims === null) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find($claims['user_id']);
        if ($user === null || $user->status !== UserStatus::Active) {
            return null;
        }

        $request->attributes->set('jwt_fp', $claims['fp']);

        return $user;
    }

    public function resolveAdmin(Request $request): ?Authenticatable
    {
        $bearer = $request->bearerToken();
        if ($bearer === null) {
            return null;
        }

        $claims = $this->jwt->parseAdminClaims($bearer);
        if ($claims === null) {
            return null;
        }

        /** @var AdminUser|null $admin */
        $admin = AdminUser::query()->find($claims['admin_id']);
        if ($admin === null || $admin->status !== AdminStatus::Active) {
            return null;
        }

        // Global "force logout all sessions": reject any admin token issued before the cutoff (R-SEC).
        $forceLogoutAt = (int) $this->settings->get('security.force_logout_at', '0');
        if ($forceLogoutAt > 0 && ($claims['issued_at'] ?? 0) < $forceLogoutAt) {
            return null;
        }

        $request->attributes->set('jwt_admin_tfa', $claims['tfa_verified']);
        if ($claims['impersonator'] !== null) {
            $request->attributes->set('jwt_impersonator_id', $claims['impersonator']);
        }

        return $admin;
    }
}
