<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Auth\Services\WidgetTokenService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /v1/me/widget-token — issueWidgetToken (auth:user, a normal app session). Hands this install a fresh
 * open-only widget token (the previous one is revoked) — see WidgetTokenService. The app stores it in secure
 * storage for the home-screen widget's background isolate and re-asks while it is used.
 */
class WidgetTokenController
{
    public function issue(Request $request, UserDeviceService $devices, WidgetTokenService $tokens, JwtService $jwt): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $bearer = $request->bearerToken();
        $fingerprint = $bearer !== null ? ($jwt->parseUserClaims($bearer)['fp'] ?? null) : null;
        $device = $devices->findByFingerprint((int) $user->getKey(), $fingerprint);
        abort_if($device === null, 409, 'No install is bound to this session.');

        ['token' => $token, 'model' => $model] = $tokens->issue($user, $device);

        return response()->json(['data' => [
            'token' => $token,
            'expires_at' => $model->expires_at->toIso8601String(),
        ]], 201);
    }
}
