<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Events\EmailOtpRequested;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Users\Models\User;

/**
 * Resend the outstanding email OTP. Picks the purpose from the account state — an unverified account
 * gets a registration OTP, a verified one a login OTP. Anti-enumeration: identical timing envelope
 * whether or not the account exists. Per-request throttling is the `otp-resend` HTTP limiter.
 */
final class ResendEmailOtp
{
    public function __construct(private readonly OtpService $otp) {}

    /** @return array{expires_in_seconds: int, resend_available_in_seconds: int} */
    public function handle(string $email, string $ip, string $locale): array
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            $purpose = $user->email_verified_at === null ? OtpPurpose::EmailVerify : OtpPurpose::Login;
            $this->otp->issueToEmail($email, $purpose, $ip, $locale);
            EmailOtpRequested::dispatch(EmailMask::mask($email), $purpose->value);
        }

        return [
            'expires_in_seconds' => (int) config('domain.auth.otp.ttl_seconds'),
            'resend_available_in_seconds' => (int) config('domain.auth.otp.resend_seconds'),
        ];
    }
}
