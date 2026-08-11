<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\DeviceFingerprintData;
use App\Domain\Auth\Enums\AuthActorKind;
use App\Domain\Auth\Enums\AuthOutcome;
use App\Domain\Auth\Events\UserAuthenticated;
use App\Domain\Auth\Exceptions\OtpVerificationException;
use App\Domain\Auth\Services\AuthAttemptRecorder;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Services\RefreshTokenService;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Auth\Support\AuthTokens;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Verify an OTP and exchange it for tokens (openapi verifyOtp). First login auto-provisions
 * the user (phone is canonical — R-DOM-01); the install is registered/refreshed; an opaque
 * refresh token and a 15-minute RS256 access token are issued. Transaction boundary here
 * (R-ARCH-05).
 */
final class VerifyOtpAndIssueTokens
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly UserDeviceService $devices,
        private readonly RefreshTokenService $refreshTokens,
        private readonly JwtService $jwt,
        private readonly AuthAttemptRecorder $attempts,
    ) {}

    public function handle(string $phone, string $code, DeviceFingerprintData $device, string $ip, ?string $userAgent): AuthTokens
    {
        $this->otp->verify($phone, $code);

        $tokens = DB::transaction(function () use ($phone, $device, $ip, $userAgent): AuthTokens {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['phone' => $phone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => UserStatus::Active->value],
            );

            // Never issue tokens to a blocked account (generic failure — no enumeration).
            if ($user->status !== UserStatus::Active) {
                throw OtpVerificationException::wrongCode();
            }

            $userDevice = $this->devices->registerOrUpdate($user, $device, $ip);
            $refresh = $this->refreshTokens->issue($user, $userDevice, $ip, $userAgent);
            $access = $this->jwt->issueUserAccessToken((int) $user->getKey(), $device->installUuid);

            return new AuthTokens(
                accessToken: $access->token,
                expiresIn: $access->expiresIn,
                refreshToken: $refresh['plaintext'],
                refreshExpiresIn: (int) config('domain.auth.refresh.ttl_days') * 86400,
                user: $user,
            );
        });

        $userDevice = $this->devices->findByFingerprint((int) $tokens->user->getKey(), $device->installUuid);

        $this->attempts->record(AuthActorKind::User, $phone, AuthOutcome::Success, $ip, $userAgent);
        if ($userDevice !== null) {
            UserAuthenticated::dispatch($tokens->user, $userDevice, 'otp', $ip);
        }

        return $tokens;
    }
}
