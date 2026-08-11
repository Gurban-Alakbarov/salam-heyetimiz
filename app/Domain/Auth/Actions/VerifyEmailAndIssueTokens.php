<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\DeviceFingerprintData;
use App\Domain\Auth\Enums\AuthActorKind;
use App\Domain\Auth\Enums\AuthOutcome;
use App\Domain\Auth\Events\UserAuthenticated;
use App\Domain\Auth\Events\UserRegistered;
use App\Domain\Auth\Exceptions\OtpVerificationException;
use App\Domain\Auth\Services\AuthAttemptRecorder;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Services\RefreshTokenService;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Auth\Support\AuthTokens;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Auth\Support\VerifyResult;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * The SINGLE email-OTP verify flow shared by registration AND email login (brief §3 — no duplication).
 * The OTP is verified first (standalone, so a wrong-code attempt counter persists); then, in ONE
 * transaction (brief §6), email_verified_at + last_login_at + last_login_ip + the user_device row +
 * the refresh token + the access token are all written together — no partial state. `wasRegistration`
 * is true when this call flipped email_verified_at from null.
 */
final class VerifyEmailAndIssueTokens
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly UserDeviceService $devices,
        private readonly RefreshTokenService $refreshTokens,
        private readonly JwtService $jwt,
        private readonly AuthAttemptRecorder $attempts,
        private readonly Clock $clock,
    ) {}

    public function handle(string $email, string $code, DeviceFingerprintData $device, string $ip, ?string $userAgent): VerifyResult
    {
        $email = mb_strtolower(trim($email));
        $this->otp->verifyByEmail($email, $code, null); // matches the latest outstanding code for the email

        /** @var array{0: AuthTokens, 1: bool} $result */
        $result = DB::transaction(function () use ($email, $device, $ip, $userAgent): array {
            /** @var User|null $user */
            $user = User::query()->where('email', $email)->lockForUpdate()->first();
            if ($user === null || $user->status !== UserStatus::Active) {
                // Generic refusal — no enumeration, no partial state (nothing was written yet).
                throw OtpVerificationException::wrongCode();
            }

            $wasRegistration = $user->email_verified_at === null;
            $now = $this->clock->now();

            $user->forceFill([
                'email_verified_at' => $user->email_verified_at ?? $now,
                'last_login_at' => $now,
                'last_login_ip' => $ip,
            ])->save();

            $userDevice = $this->devices->registerOrUpdate($user, $device, $ip);
            $refresh = $this->refreshTokens->issue($user, $userDevice, $ip, $userAgent);
            $access = $this->jwt->issueUserAccessToken((int) $user->getKey(), $device->installUuid);

            return [
                new AuthTokens(
                    accessToken: $access->token,
                    expiresIn: $access->expiresIn,
                    refreshToken: $refresh['plaintext'],
                    refreshExpiresIn: (int) config('domain.auth.refresh.ttl_days') * 86400,
                    user: $user,
                ),
                $wasRegistration,
            ];
        });

        [$tokens, $wasRegistration] = $result;

        $userDevice = $this->devices->findByFingerprint((int) $tokens->user->getKey(), $device->installUuid);
        $this->attempts->record(AuthActorKind::User, $email, AuthOutcome::Success, $ip, $userAgent);
        if ($wasRegistration) {
            UserRegistered::dispatch($tokens->user, EmailMask::mask($email));
        }
        if ($userDevice !== null) {
            UserAuthenticated::dispatch($tokens->user, $userDevice, 'otp', $ip);
        }

        return new VerifyResult($tokens, $wasRegistration);
    }
}
