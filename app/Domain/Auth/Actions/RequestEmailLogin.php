<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Events\EmailOtpRequested;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Users\Models\User;

/**
 * Request a login OTP for a returning, verified user. Anti-enumeration: the response (timing envelope)
 * is identical whether or not a verified account exists — the OTP is only dispatched when it does.
 */
final class RequestEmailLogin
{
    public function __construct(private readonly OtpService $otp) {}

    /** @return array{expires_in_seconds: int, resend_available_in_seconds: int} */
    public function handle(string $email, string $ip, string $locale): array
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->whereNotNull('email_verified_at')->first();

        if ($user !== null) {
            $this->otp->issueToEmail($email, OtpPurpose::Login, $ip, $locale);
            EmailOtpRequested::dispatch(EmailMask::mask($email), OtpPurpose::Login->value);
        }

        return [
            'expires_in_seconds' => (int) config('domain.auth.otp.ttl_seconds'),
            'resend_available_in_seconds' => (int) config('domain.auth.otp.resend_seconds'),
        ];
    }
}
