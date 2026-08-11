<?php

namespace App\Http\Admin\V1\Controllers\Auth;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Actions\AdminLogin;
use App\Domain\Auth\Actions\AdminLogout;
use App\Domain\Auth\Actions\AdminVerifyTwoFactor;
use App\Domain\Auth\Actions\RegenerateRecoveryCodes;
use App\Http\Admin\V1\Requests\Auth\AdminLoginRequest;
use App\Http\Admin\V1\Requests\Auth\AdminVerify2faRequest;
use App\Http\Admin\V1\Requests\Auth\RegenerateRecoveryCodesRequest;
use App\Http\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuthController
{
    /** POST /admin/v1/auth/login — adminLogin. 200 with a 2FA challenge, or a full session if 2FA is off. */
    public function login(AdminLoginRequest $request, AdminLogin $action): JsonResponse
    {
        $result = $action->handle(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Global 2FA toggle OFF → email + password was enough; return the access token directly.
        if (($result['two_factor_required'] ?? true) === false) {
            return response()->json([
                'two_factor_required' => false,
                'access_token' => $result['token']->token,
                'token_type' => 'Bearer',
                'expires_in' => $result['token']->expiresIn,
                'admin' => (new AdminUserResource($result['admin']))->toArray($request),
            ], 200);
        }

        return response()->json([
            'two_factor_required' => true,
            'challenge_token' => $result['challenge_token'],
            'expires_in_seconds' => $result['expires_in_seconds'],
            'requires_totp' => $result['requires_totp'],
        ], 200);
    }

    /** POST /admin/v1/auth/2fa/verify — adminVerify2fa (200 AdminAuthSuccess). */
    public function verify2fa(AdminVerify2faRequest $request, AdminVerifyTwoFactor $action): JsonResponse
    {
        $result = $action->handle(
            challengeToken: (string) $request->input('challenge_token'),
            totp: $request->totp(),
            recoveryCode: $request->recoveryCode(),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'access_token' => $result['token']->token,
            'token_type' => 'Bearer',
            'expires_in' => $result['token']->expiresIn,
            'admin' => (new AdminUserResource($result['admin']))->toArray($request),
            'used_recovery_code' => $result['used_recovery_code'],
            'recovery_codes_remaining' => $result['recovery_codes_remaining'],
        ], 200);
    }

    /** POST /admin/v1/auth/logout — adminLogout (204). */
    public function logout(Request $request, AdminLogout $action): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();
        $action->handle($admin);

        return response()->json(null, 204);
    }

    /** GET /admin/v1/auth/me — adminMe (200 AdminUser). */
    public function me(Request $request): AdminUserResource
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        return new AdminUserResource($admin);
    }

    /** POST /admin/v1/auth/recovery-codes — regenerateRecoveryCodes (200, codes shown once). */
    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request, RegenerateRecoveryCodes $action): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $result = $action->handle($admin, (string) $request->input('totp'));

        return response()->json([
            'codes' => $result['codes'],
            'generated_at' => $result['generated_at']->toIso8601String(),
        ], 200);
    }
}
