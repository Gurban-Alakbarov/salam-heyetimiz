<?php

namespace App\Http\Api\V1\Controllers\Notifications;

use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Users\Models\User;
use App\Http\Api\V1\Requests\Notifications\UpsertPushTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-install FCM push-token lifecycle (openapi upsertPushToken / deletePushToken). Operates on the
 * calling install only, resolved from the access token's `fp` (install_uuid) claim; the token lives on
 * that user_devices row, so multi-device tokens stay isolated. The mutation runs through the Auth
 * UserDeviceService (R-ARCH-06) — Notifications never writes user_devices directly.
 */
class PushTokenController
{
    public function __construct(private readonly UserDeviceService $devices) {}

    /** PUT /v1/notifications/push-token — upsertPushToken (204). */
    public function upsert(UpsertPushTokenRequest $request): JsonResponse
    {
        $this->devices->upsertPushToken(
            $this->user($request),
            $this->fingerprint($request),
            (string) $request->input('push_token'),
        );

        return response()->json(null, 204);
    }

    /** DELETE /v1/notifications/push-token — deletePushToken (204, no body). */
    public function destroy(Request $request): JsonResponse
    {
        $this->devices->clearPushToken($this->user($request), $this->fingerprint($request));

        return response()->json(null, 204);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** Current install id from the access token's `fp` claim — read straight from the token (R-AUTH). */
    private function fingerprint(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        return $bearer === null ? null : (app(JwtService::class)->parseUserClaims($bearer)['fp'] ?? null);
    }
}
