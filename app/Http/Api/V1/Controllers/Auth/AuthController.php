<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Auth\Actions\LogoutUser;
use App\Domain\Auth\Actions\RefreshTokens;
use App\Domain\Auth\Actions\SendOtp;
use App\Domain\Auth\Actions\VerifyOtpAndIssueTokens;
use App\Domain\Auth\Support\AuthTokens;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Domain\Users\Models\User;
use App\Http\Api\V1\Requests\Auth\RefreshTokenRequest;
use App\Http\Api\V1\Requests\Auth\RequestOtpRequest;
use App\Http\Api\V1\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserSelfResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(private readonly SubscriptionQuery $subscriptions) {}

    /** POST /v1/auth/otp/request — requestOtp (202, anti-enumeration). */
    public function requestOtp(RequestOtpRequest $request, SendOtp $action): JsonResponse
    {
        $result = $action->handle(
            phone: (string) $request->input('phone'),
            purpose: $request->purpose(),
            ip: (string) $request->ip(),
            locale: app()->getLocale(),
        );

        return response()->json($result, 202);
    }

    /** POST /v1/auth/otp/verify — verifyOtp (200 AuthSuccess). */
    public function verifyOtp(VerifyOtpRequest $request, VerifyOtpAndIssueTokens $action): JsonResponse
    {
        $tokens = $action->handle(
            phone: (string) $request->input('phone'),
            code: (string) $request->input('code'),
            device: $request->deviceData(),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $this->authSuccess($tokens);
    }

    /** POST /v1/auth/refresh — refreshToken (200 AuthSuccess, rotated). */
    public function refresh(RefreshTokenRequest $request, RefreshTokens $action): JsonResponse
    {
        $tokens = $action->handle(
            presentedToken: (string) $request->input('refresh_token'),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return $this->authSuccess($tokens);
    }

    /** POST /v1/auth/logout — logout (204). */
    public function logout(Request $request, LogoutUser $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $this->fingerprint($request));

        return response()->json(null, 204);
    }

    private function authSuccess(AuthTokens $tokens): JsonResponse
    {
        $user = $tokens->user;
        $user->has_active_subscription = $this->subscriptions->hasActiveForUser((int) $user->getKey());

        return response()->json([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokens->expiresIn,
            'refresh_expires_in' => $tokens->refreshExpiresIn,
            'user' => (new UserSelfResource($user))->toArray(request()),
        ], 200);
    }

    private function fingerprint(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        return $bearer === null ? null : (app(\App\Domain\Auth\Services\JwtService::class)->parseUserClaims($bearer)['fp'] ?? null);
    }
}
