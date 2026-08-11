<?php

namespace App\Domain\Auth\Services;

use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Auth\Enums\AuthActorKind;
use App\Domain\Auth\Enums\AuthOutcome;
use App\Domain\Auth\Events\AdminAuthenticated;
use App\Domain\Auth\Events\AdminLoginFailed;
use App\Domain\Auth\Events\RecoveryCodesRegenerated;
use App\Domain\Auth\Exceptions\AdminLoginException;
use App\Domain\Auth\Support\IssuedAccessToken;
use App\Domain\Auth\Support\RecoveryCodes;
use App\Domain\Auth\Support\Totp;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\Hash;

/**
 * Two-phase admin authentication (R-SEC-05/06): step 1 verifies email + bcrypt password
 * (with failed-attempt lockout) and returns a short-lived 2FA challenge; step 2 verifies a
 * TOTP code or a single-use recovery code and issues the 30-minute admin JWT carrying
 * tfa_verified=true. Recovery-code use consumes the code; regeneration replaces the set.
 */
final class AdminAuthService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly JwtService $jwt,
        private readonly AuthAttemptRecorder $attempts,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Step 1 — email + password. When the global `security.require_2fa` toggle is ON, returns a 2FA
     * challenge envelope; when OFF, logs the admin in directly (email + password only). Throws on 401.
     *
     * @return array<string, mixed> two_factor_required + either {challenge_token,…} or {admin, token}
     */
    public function login(string $email, string $password, string $ip, ?string $userAgent): array
    {
        /** @var AdminUser|null $admin */
        $admin = AdminUser::query()->where('email', $email)->first();

        if ($admin === null) {
            $this->fail($email, 'invalid_credentials', AuthOutcome::WrongCredential, $ip, $userAgent);
            throw AdminLoginException::invalidCredentials();
        }

        if ($admin->status !== AdminStatus::Active) {
            $this->fail($email, 'account_locked', AuthOutcome::Locked, $ip, $userAgent);
            throw AdminLoginException::accountLocked();
        }

        if ($admin->locked_until !== null && $admin->locked_until->greaterThan($this->clock->now())) {
            $this->fail($email, 'account_locked', AuthOutcome::Locked, $ip, $userAgent);
            throw AdminLoginException::accountLocked();
        }

        if (! Hash::check($password, (string) $admin->password)) {
            $this->registerFailedPassword($admin);
            $this->fail($email, 'invalid_credentials', AuthOutcome::WrongCredential, $ip, $userAgent);
            throw AdminLoginException::invalidCredentials();
        }

        // Correct password → clear the failure counter.
        $admin->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();

        // Global 2FA toggle OFF → log the admin in directly (email + password only).
        if (! $this->settings->bool(SettingsService::REQUIRE_2FA, false)) {
            $token = $this->jwt->issueAdminAccessToken((int) $admin->getKey(), $admin->role->value);
            $this->attempts->record(AuthActorKind::Admin, $admin->email, AuthOutcome::Success, $ip, $userAgent);
            AdminAuthenticated::dispatch($admin, false, $ip);

            return ['two_factor_required' => false, 'admin' => $admin, 'token' => $token];
        }

        // 2FA ON → issue the short-lived challenge; the client must submit a TOTP / recovery code.
        return [
            'two_factor_required' => true,
            'challenge_token' => $this->jwt->issueAdminChallengeToken((int) $admin->getKey()),
            'expires_in_seconds' => (int) config('domain.auth.challenge.ttl_seconds'),
            'requires_totp' => (bool) $admin->is_2fa_enabled && $admin->totp_secret !== null,
        ];
    }

    /**
     * Step 2 — submit TOTP or recovery code. Returns the admin token + result, or throws.
     *
     * @return array{admin: AdminUser, token: IssuedAccessToken, used_recovery_code: bool, recovery_codes_remaining: int}
     */
    public function verifyTwoFactor(string $challengeToken, ?string $totp, ?string $recoveryCode, string $ip, ?string $userAgent): array
    {
        $adminId = $this->jwt->readChallengeAdminId($challengeToken);
        if ($adminId === null) {
            throw AdminLoginException::challengeExpired();
        }

        /** @var AdminUser|null $admin */
        $admin = AdminUser::query()->find($adminId);
        if ($admin === null || $admin->status !== AdminStatus::Active) {
            throw AdminLoginException::challengeExpired();
        }

        $usedRecovery = false;

        if ($totp !== null) {
            if (! $this->verifyTotp($admin, $totp)) {
                $this->fail($admin->email, 'wrong_totp', AuthOutcome::TwoFactorFailed, $ip, $userAgent);
                throw AdminLoginException::wrongTotp();
            }
        } else {
            if (! $this->consumeRecoveryCode($admin, (string) $recoveryCode)) {
                $this->fail($admin->email, 'wrong_recovery_code', AuthOutcome::TwoFactorFailed, $ip, $userAgent);
                throw AdminLoginException::wrongRecoveryCode();
            }
            $usedRecovery = true;
        }

        $token = $this->jwt->issueAdminAccessToken((int) $admin->getKey(), $admin->role->value);

        $this->attempts->record(AuthActorKind::Admin, $admin->email, AuthOutcome::Success, $ip, $userAgent);
        AdminAuthenticated::dispatch($admin, $usedRecovery, $ip);

        return [
            'admin' => $admin,
            'token' => $token,
            'used_recovery_code' => $usedRecovery,
            'recovery_codes_remaining' => RecoveryCodes::remaining((array) $admin->recovery_codes_hashes),
        ];
    }

    /**
     * Re-auth + regenerate the recovery-code set (R-SEC-06). Returns the plaintext codes once.
     *
     * @return array{codes: array<int, string>, generated_at: \Carbon\CarbonImmutable}
     */
    public function regenerateRecoveryCodes(AdminUser $admin, string $totp): array
    {
        if (! $this->verifyTotp($admin, $totp)) {
            throw AdminLoginException::wrongTotp();
        }

        $set = RecoveryCodes::generate(
            (int) config('domain.auth.recovery_codes.count'),
            (int) config('domain.auth.recovery_codes.length_bytes'),
        );

        $now = $this->clock->now();
        $admin->forceFill([
            'recovery_codes_hashes' => $set['hashes'],
            'recovery_codes_generated_at' => $now,
        ])->save();

        RecoveryCodesRegenerated::dispatch((int) $admin->getKey());

        return ['codes' => $set['plaintext'], 'generated_at' => $now];
    }

    private function verifyTotp(AdminUser $admin, string $code): bool
    {
        $secret = $admin->totp_secret;

        return is_string($secret) && $secret !== '' && Totp::fromConfig()->verify($secret, $code);
    }

    private function consumeRecoveryCode(AdminUser $admin, string $candidate): bool
    {
        $hashes = (array) $admin->recovery_codes_hashes;
        $index = RecoveryCodes::match($hashes, $candidate);

        if ($index === null) {
            return false;
        }

        $hashes[$index] = null; // single-use: consume the slot
        $admin->forceFill(['recovery_codes_hashes' => array_values($hashes)])->save();

        return true;
    }

    private function registerFailedPassword(AdminUser $admin): void
    {
        $failed = (int) $admin->failed_login_count + 1;
        $max = (int) config('domain.auth.admin_login.max_failed');

        $attributes = ['failed_login_count' => $failed];
        if ($failed >= $max) {
            $attributes['locked_until'] = $this->clock->now()
                ->addMinutes((int) config('domain.auth.admin_login.lockout_minutes'));
        }

        $admin->forceFill($attributes)->save();
    }

    private function fail(string $email, string $reason, AuthOutcome $outcome, string $ip, ?string $userAgent): void
    {
        $this->attempts->record(AuthActorKind::Admin, $email, $outcome, $ip, $userAgent);
        AdminLoginFailed::dispatch($email, $reason);
    }
}
