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
    case ResidentInvitation = 'resident_invitation';
    case FamilyInvitation = 'family_invitation';
    case ApplicationRejected = 'application_rejected';
    case PaymentReceipt = 'payment_receipt';
    case RefundReceipt = 'refund_receipt';
    case SiteLead = 'site_lead';

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
            self::RegistrationOtp => 'Salam Sesame — qeydiyyat təsdiq kodu',
            self::LoginOtp => 'Salam Sesame — giriş təsdiq kodu',
            self::ResidentInvitation => 'Salam Sesame — yaşayış kompleksinə dəvət',
            self::FamilyInvitation => 'Salam Sesame — ailə üzvü dəvəti',
            self::ApplicationRejected => 'Salam Sesame — müraciətiniz təsdiqlənmədi',
            self::PaymentReceipt => 'Salam Sesame — ödəniş qəbzi',
            self::RefundReceipt => 'Salam Sesame — geri qaytarma qəbzi',
            self::SiteLead => 'Salam Sesame — saytdan yeni müraciət',
        };
    }
}
