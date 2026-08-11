<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Auth\Actions\SetBiometricEnrollment;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-install biometric-unlock opt-in (openapi enrollBiometrics / disableBiometrics).
 * Operates on the calling install, resolved from the access token's fingerprint claim.
 */
class BiometricController
{
    /** POST /v1/me/biometrics/enroll — enrollBiometrics (204). */
    public function enroll(Request $request, SetBiometricEnrollment $action): JsonResponse
    {
        $action->handle($this->user($request), $this->fingerprint($request), true);

        return response()->json(null, 204);
    }

    /** DELETE /v1/me/biometrics — disableBiometrics (204). */
    public function disable(Request $request, SetBiometricEnrollment $action): JsonResponse
    {
        $action->handle($this->user($request), $this->fingerprint($request), false);

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function fingerprint(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        return $bearer === null ? null : (app(\App\Domain\Auth\Services\JwtService::class)->parseUserClaims($bearer)['fp'] ?? null);
    }
}
