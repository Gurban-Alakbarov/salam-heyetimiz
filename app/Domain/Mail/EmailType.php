<?php

namespace App\Domain\Mail;

/**
 * The catalog of transactional email types the platform sends. This module wires only the two OTP
 * types; the rest are reserved so future emails (Welcome, Password*, Email changed, Security alerts)
 * are a template + a settings subject away — no infrastructure change. See
 * docs/registration/USER_REGISTRATION_ARCHITECTURE.md §7.
 */
enum EmailType: string
{
    case RegistrationOtp = 'registration_otp';
    case LoginOtp = 'login_otp';
    case Welcome = 'welcome';
    case PasswordSet = 'password_set';
    case PasswordChanged = 'password_changed';
    case EmailChanged = 'email_changed';
    case SecurityAlert = 'security_alert';

    /** Blade view that renders this email (both OTP types share the one OTP template). */
    public function view(): string
    {
        return match ($this) {
            self::RegistrationOtp, self::LoginOtp => 'emails.otp',
            default => 'emails.'.str_replace('_', '-', $this->value),
        };
    }

    /** Settings key under the `email` group holding this type's (admin-editable) subject line. */
    public function subjectKey(): string
    {
        return 'subject_'.$this->value;
    }

    /** Fallback subject used when the Settings value is blank. */
    public function defaultSubject(): string
    {
        return match ($this) {
            self::RegistrationOtp => 'Salam Həyətimiz — qeydiyyat təsdiq kodu',
            self::LoginOtp => 'Salam Həyətimiz — giriş təsdiq kodu',
            self::Welcome => 'Salam Həyətimiz-ə xoş gəldiniz',
            self::PasswordSet => 'Salam Həyətimiz — parol təyin edildi',
            self::PasswordChanged => 'Salam Həyətimiz — parol dəyişdirildi',
            self::EmailChanged => 'Salam Həyətimiz — email dəyişdirildi',
            self::SecurityAlert => 'Salam Həyətimiz — təhlükəsizlik bildirişi',
        };
    }
}
