<?php

namespace App\Domain\Admin\Settings;

/**
 * The single source of truth for every system setting — grouped by tab. Each definition is
 * [type, default, label, (options for select), (secret => true)]. The full storage key is "{group}.{key}".
 * Defaults reuse the existing config/* values so nothing is lost. Adding a setting is one array entry —
 * the API, the seeded default, the encryption and the generic UI all pick it up with no other code change.
 *
 * type: string | int | money | bool | secret | select | readonly
 *   money — stored in minor units (qəpik), edited in AZN in the panel.
 * Optional: min / max (int and money, enforced on save) and hint (shown under the field).
 */
final class SettingsCatalog
{
    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'app_name' => ['type' => 'string', 'default' => 'Salam Həyətimiz', 'label' => 'Tətbiq adı'],
                'maintenance_mode' => ['type' => 'bool', 'default' => false, 'label' => 'Texniki rejim'],
                'min_version' => ['type' => 'string', 'default' => '1.0.0', 'label' => 'Minimum tətbiq versiyası'],
                'support_email' => ['type' => 'string', 'default' => '', 'label' => 'Dəstək e-poçtu'],
                'support_phone' => ['type' => 'string', 'default' => '', 'label' => 'Dəstək telefonu'],
                'site_lead_email' => ['type' => 'string', 'default' => '', 'label' => 'Sayt müraciətləri üçün e-poçt'],
            ],
            'security' => [
                'require_2fa' => ['type' => 'bool', 'default' => false, 'label' => 'İki-faktorlu doğrulama tələb et'],
                'jwt_user_ttl_seconds' => ['type' => 'int', 'default' => 900, 'label' => 'Mobil JWT ömrü (saniyə)'],
                'jwt_admin_ttl_seconds' => ['type' => 'int', 'default' => 1800, 'label' => 'Admin JWT ömrü (saniyə)'],
                'refresh_ttl_days' => ['type' => 'int', 'default' => 60, 'label' => 'Refresh token ömrü (gün)'],
                'max_login_attempts' => ['type' => 'int', 'default' => 5, 'label' => 'Maksimum login cəhdi'],
                'lockout_minutes' => ['type' => 'int', 'default' => 15, 'label' => 'Bloklama müddəti (dəq)'],
                'password_min_length' => ['type' => 'int', 'default' => AdminPasswordPolicy::FLOOR, 'label' => 'Minimum parol uzunluğu', 'min' => AdminPasswordPolicy::FLOOR, 'max' => 64,
                    'hint' => 'Yeni admin yaradılanda və parol dəyişəndə yoxlanır.'],
                'password_require_uppercase' => ['type' => 'bool', 'default' => true, 'label' => 'Parol: böyük hərf tələb et'],
                'password_require_number' => ['type' => 'bool', 'default' => true, 'label' => 'Parol: rəqəm tələb et'],
                'password_require_symbol' => ['type' => 'bool', 'default' => false, 'label' => 'Parol: simvol tələb et'],
            ],
            'email' => [
                'smtp_host' => ['type' => 'string', 'default' => '', 'label' => 'SMTP Host'],
                'smtp_port' => ['type' => 'int', 'default' => 587, 'label' => 'SMTP Port'],
                'smtp_username' => ['type' => 'string', 'default' => '', 'label' => 'SMTP İstifadəçi'],
                'smtp_password' => ['type' => 'secret', 'default' => '', 'label' => 'SMTP Parol'],
                'smtp_encryption' => ['type' => 'select', 'default' => 'tls', 'label' => 'Şifrələmə', 'options' => ['none', 'tls', 'ssl']],
                'from_name' => ['type' => 'string', 'default' => 'Salam Həyətimiz', 'label' => 'From Name (göndərən ad)'],
                'from_email' => ['type' => 'string', 'default' => '', 'label' => 'From Email (göndərən e-poçt)'],
                'reply_to_email' => ['type' => 'string', 'default' => '', 'label' => 'Reply-To e-poçt'],
                'html_enabled' => ['type' => 'bool', 'default' => true, 'label' => 'HTML email şablonları aktiv'],
                'subject_registration_otp' => ['type' => 'string', 'default' => 'Salam Həyətimiz — qeydiyyat təsdiq kodu', 'label' => 'Mövzu — Qeydiyyat OTP'],
                'subject_login_otp' => ['type' => 'string', 'default' => 'Salam Həyətimiz — giriş təsdiq kodu', 'label' => 'Mövzu — Giriş OTP'],
                'subject_resident_invitation' => ['type' => 'string', 'default' => 'Salam Sesame — yaşayış kompleksinə dəvət', 'label' => 'Mövzu — Sakin dəvəti'],
                'subject_family_invitation' => ['type' => 'string', 'default' => 'Salam Sesame — ailə üzvü dəvəti', 'label' => 'Mövzu — Ailə üzvü dəvəti'],
                'subject_application_rejected' => ['type' => 'string', 'default' => 'Salam Sesame — müraciətiniz təsdiqlənmədi', 'label' => 'Mövzu — Müraciətin rəddi'],
                'subject_payment_receipt' => ['type' => 'string', 'default' => 'Salam Sesame — ödəniş qəbzi', 'label' => 'Mövzu — Ödəniş qəbzi'],
                'subject_refund_receipt' => ['type' => 'string', 'default' => 'Salam Sesame — geri qaytarma qəbzi', 'label' => 'Mövzu — Geri qaytarma qəbzi'],
                'subject_site_lead' => ['type' => 'string', 'default' => 'Salam Sesame — saytdan yeni müraciət', 'label' => 'Mövzu — Saytdan müraciət'],
            ],
            'otp' => [
                'otp_length' => ['type' => 'int', 'default' => 6, 'label' => 'OTP uzunluğu'],
                'otp_ttl_seconds' => ['type' => 'int', 'default' => 120, 'label' => 'OTP ömrü / bitmə (saniyə)'],
                'otp_resend_seconds' => ['type' => 'int', 'default' => 30, 'label' => 'Yenidən göndərmə intervalı (san)'],
                'otp_max_attempts' => ['type' => 'int', 'default' => 5, 'label' => 'Maksimum cəhd'],
                'otp_daily_limit' => ['type' => 'int', 'default' => 0, 'label' => 'Günlük limit (0=limitsiz)'],
                'otp_hourly_limit' => ['type' => 'int', 'default' => 0, 'label' => 'Saatlıq limit (0=limitsiz)'],
                'otp_temp_block_minutes' => ['type' => 'int', 'default' => 0, 'label' => 'Müvəqqəti blok müddəti (dəq, 0=yox)'],
                'tpl_registration' => ['type' => 'text', 'default' => 'Qeydiyyat kodunuz: {code}', 'label' => 'Şablon — Qeydiyyat OTP'],
                'tpl_login' => ['type' => 'text', 'default' => 'Giriş kodunuz: {code}', 'label' => 'Şablon — Giriş OTP'],
            ],
            'payments' => [
                'kapital_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Kapital Bank aktiv'],
                'kapital_mode' => ['type' => 'select', 'default' => 'sandbox', 'label' => 'Rejim', 'options' => ['sandbox', 'production']],
                'kapital_ecom_username' => ['type' => 'string', 'default' => '', 'label' => 'E-commerce login'],
                'kapital_ecom_password' => ['type' => 'secret', 'default' => '', 'label' => 'E-commerce şifrə'],
                'kapital_return_url' => ['type' => 'string', 'default' => '', 'label' => 'Return URL'],
                'kapital_currency' => ['type' => 'string', 'default' => 'AZN', 'label' => 'Valyuta'],
                'kapital_timeout_seconds' => ['type' => 'int', 'default' => 15, 'label' => 'HTTP timeout (san)'],
                'kapital_connect_timeout_seconds' => ['type' => 'int', 'default' => 5, 'label' => 'Connect timeout (san)'],
                'kapital_retries' => ['type' => 'int', 'default' => 3, 'label' => 'Təkrar sayı'],
                'kapital_callback_timeout_minutes' => ['type' => 'int', 'default' => 30, 'label' => 'Sifariş gözləmə (dəq)'],
                'kapital_authorising_recheck_minutes' => ['type' => 'int', 'default' => 3, 'label' => 'Recheck pəncərəsi (dəq)'],
                'kapital_refund_window_days' => ['type' => 'int', 'default' => 365, 'label' => 'Refund pəncərəsi (gün)'],
            ],
            'traccar' => [
                'offline_threshold_minutes' => ['type' => 'int', 'default' => (int) config('domain.devices.offline_threshold_minutes', 15), 'label' => 'Offline həddi (dəq)', 'min' => 1, 'max' => 1440,
                    'hint' => 'Cihazdan bu qədər dəqiqə siqnal gəlməsə, Offline sayılır.'],
            ],
            'subscriptions' => [
                // LIVE: the price and term of every new subscription and paid renewal (SubscriptionPriceResolver).
                'price_main_minor' => ['type' => 'money', 'default' => (int) config('domain.subscriptions.default_prices_minor.sub_main', 1500), 'label' => 'Sakin abunəliyinin qiyməti (AZN)', 'min' => 100, 'max' => 100000,
                    'hint' => 'Yeni alışlara və yeniləmələrə dərhal tətbiq olunur. Aktiv abunəliklər bitənə qədər dəyişmir.'],
                'price_additional_minor' => ['type' => 'money', 'default' => (int) config('domain.subscriptions.default_prices_minor.sub_additional', 1500), 'label' => 'Ailə üzvü abunəliyinin qiyməti (AZN)', 'min' => 100, 'max' => 100000],
                'subscription_duration_days' => ['type' => 'int', 'default' => (int) config('domain.subscriptions.term_days', 365), 'label' => 'Abunəlik müddəti (gün)', 'min' => 30, 'max' => 730,
                    'hint' => '365 = 1 il. Bir ödəniş bu qədər gün giriş verir.'],
            ],
        ];
    }

    /** Definition for a "group.key" or null. @return array<string, mixed>|null */
    public static function definition(string $group, string $key): ?array
    {
        return self::groups()[$group][$key] ?? null;
    }

    /** @return array<int, string> group names */
    public static function groupNames(): array
    {
        return array_keys(self::groups());
    }
}
