<?php

namespace App\Domain\Auth\Adapters;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Auth\Contracts\EmailOtpTransport;
use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;

/**
 * Production email-OTP transport: renders the OTP through the generic {@see TemplatedMailer} and sends
 * via the runtime SMTP (Brevo). The short intro line is the admin-editable `otp.tpl_registration` /
 * `otp.tpl_login` setting (with the `{code}` placeholder stripped — the code renders separately);
 * the TTL minutes come from `domain.auth.otp.ttl_seconds`, which the Settings bridge drives. No
 * hardcoded TTL (R-SEC-03).
 */
final class TemplatedEmailOtpTransport implements EmailOtpTransport
{
    public function __construct(
        private readonly TemplatedMailer $mailer,
        private readonly SettingsService $settings,
    ) {}

    public function send(string $email, string $code, string $locale, OtpPurpose $purpose): void
    {
        $isRegistration = $purpose === OtpPurpose::EmailVerify;
        $type = $isRegistration ? EmailType::RegistrationOtp : EmailType::LoginOtp;
        $introKey = $isRegistration ? 'tpl_registration' : 'tpl_login';

        $intro = trim(str_replace('{code}', '', (string) $this->settings->value('otp', $introKey)));
        $ttlSeconds = (int) config('domain.auth.otp.ttl_seconds');
        $support = (string) ($this->settings->value('email', 'reply_to_email') ?: $this->settings->value('email', 'from_email'));

        $this->mailer->send($type, $email, [
            'code' => $code,
            'ttlMinutes' => max(1, (int) ceil($ttlSeconds / 60)),
            'intro' => $intro,
            'brand' => 'Salam Həyətimiz',
            'supportEmail' => $support,
        ], $locale);
    }
}
