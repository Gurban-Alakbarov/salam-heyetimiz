<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Auth\Actions\RegisterUser;
use App\Domain\Auth\Actions\RequestEmailLogin;
use App\Domain\Auth\Actions\ResendEmailOtp;
use App\Domain\Auth\Actions\VerifyEmailAndIssueTokens;
use App\Domain\Auth\Exceptions\OtpVerificationException;
use App\Domain\Auth\Exceptions\RegistrationException;
use App\Domain\Auth\Support\AuthTokens;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Http\Api\V1\Requests\Auth\EmailLoginRequest;
use App\Http\Api\V1\Requests\Auth\RegisterRequest;
use App\Http\Api\V1\Requests\Auth\ResendOtpRequest;
use App\Http\Api\V1\Requests\Auth\VerifyEmailRequest;
use App\Http\Api\V1\Support\RespondsWithEnvelope;
use App\Http\Resources\UserSelfResource;
use Illuminate\Http\JsonResponse;

/**
 * Email-OTP registration + login (the going-forward mobile auth surface). Every response uses the unified
 * envelope `{ success, message, data, meta, errors }`. The legacy phone-OTP controller (AuthController)
 * and its endpoints are untouched. Reuses the OTP engine, JWT, refresh, device + audit infrastructure.
 */
class RegistrationController
{
    use RespondsWithEnvelope;

    public function __construct(private readonly SubscriptionQuery $subscriptions) {}

    /** POST /v1/auth/register — create/resume an unverified account + send email OTP. */
    public function register(RegisterRequest $request, RegisterUser $action): JsonResponse
    {
        try {
            $timing = $action->handle(
                firstName: (string) $request->input('first_name'),
                lastName: (string) $request->input('last_name'),
                phone: (string) $request->input('phone'),
                email: (string) $request->input('email'),
                ip: (string) $request->ip(),
                locale: app()->getLocale(),
            );
        } catch (RegistrationException $e) {
            return $this->failure($e->getMessage(), $e->payload(), $e->statusCode);
        }

        return $this->success(null, 'Təsdiq kodu email ünvanınıza göndərildi.', $timing, 202);
    }

    /** POST /v1/auth/verify-email — verify the email OTP → issue tokens (auto-login). Registration + login. */
    public function verifyEmail(VerifyEmailRequest $request, VerifyEmailAndIssueTokens $action): JsonResponse
    {
        try {
            $result = $action->handle(
                email: (string) $request->input('email'),
                code: (string) $request->input('code'),
                device: $request->deviceData(),
                ip: (string) $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (OtpVerificationException $e) {
            return $this->failure($this->otpMessage($e->errorCode()), ['code' => $e->errorCode()], 401);
        }

        return $this->tokenEnvelope(
            $result->tokens,
            $result->wasRegistration ? 'Qeydiyyat tamamlandı.' : 'Giriş uğurla tamamlandı.',
        );
    }

    /** POST /v1/auth/resend-otp — re-send the outstanding email OTP. */
    public function resendOtp(ResendOtpRequest $request, ResendEmailOtp $action): JsonResponse
    {
        $timing = $action->handle((string) $request->input('email'), (string) $request->ip(), app()->getLocale());

        return $this->success(null, 'Təsdiq kodu yenidən göndərildi.', $timing, 202);
    }

    /** POST /v1/auth/login — request an email login OTP (returning, verified user). */
    public function login(EmailLoginRequest $request, RequestEmailLogin $action): JsonResponse
    {
        $timing = $action->handle((string) $request->input('email'), (string) $request->ip(), app()->getLocale());

        return $this->success(null, 'Giriş kodu email ünvanınıza göndərildi.', $timing, 202);
    }

    private function tokenEnvelope(AuthTokens $tokens, string $message): JsonResponse
    {
        $user = $tokens->user;
        $user->has_active_subscription = $this->subscriptions->hasActiveForUser((int) $user->getKey());

        return $this->success([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokens->expiresIn,
            'refresh_expires_in' => $tokens->refreshExpiresIn,
            'user' => (new UserSelfResource($user))->toArray(request()),
        ], $message);
    }

    private function otpMessage(string $code): string
    {
        return match ($code) {
            'otp_expired' => 'Kodun vaxtı bitib. Yeni kod istəyin.',
            'otp_max_attempts' => 'Çox sayda yanlış cəhd. Yeni kod istəyin.',
            default => 'Təsdiq kodu yanlışdır.',
        };
    }
}
