<?php

namespace App\Domain\Admin\Settings;

/**
 * The single source of truth for every system setting — grouped by tab. Each definition is
 * [type, default, label, (options for select), (secret => true)]. The full storage key is "{group}.{key}".
 * Defaults reuse the existing config/* values so nothing is lost. Adding a setting is one array entry —
 * the API, the seeded default, the encryption and the generic UI all pick it up with no other code change.
 *
 * type: string | int | bool | secret | select | readonly
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
                'timezone' => ['type' => 'string', 'default' => 'Asia/Baku', 'label' => 'Saat qurşağı'],
                'default_language' => ['type' => 'select', 'default' => 'az', 'label' => 'Dil', 'options' => ['az', 'ru', 'en']],
                'maintenance_mode' => ['type' => 'bool', 'default' => false, 'label' => 'Texniki rejim'],
                'logo_url' => ['type' => 'string', 'default' => '', 'label' => 'Logo URL'],
                'favicon_url' => ['type' => 'string', 'default' => '', 'label' => 'Favicon URL'],
                'min_version' => ['type' => 'string', 'default' => '1.0.0', 'label' => 'Minimum tətbiq versiyası'],
                'latest_version' => ['type' => 'string', 'default' => '1.0.0', 'label' => 'Son tətbiq versiyası'],
                'force_update' => ['type' => 'bool', 'default' => false, 'label' => 'Məcburi yeniləmə'],
                'support_email' => ['type' => 'string', 'default' => '', 'label' => 'Dəstək e-poçtu'],
                'support_phone' => ['type' => 'string', 'default' => '', 'label' => 'Dəstək telefonu'],
            ],
            'security' => [
                'require_2fa' => ['type' => 'bool', 'default' => false, 'label' => 'İki-faktorlu doğrulama tələb et'],
                'jwt_user_ttl_seconds' => ['type' => 'int', 'default' => 900, 'label' => 'Mobil JWT ömrü (saniyə)'],
                'jwt_admin_ttl_seconds' => ['type' => 'int', 'default' => 1800, 'label' => 'Admin JWT ömrü (saniyə)'],
                'refresh_ttl_days' => ['type' => 'int', 'default' => 60, 'label' => 'Refresh token ömrü (gün)'],
                'session_timeout_minutes' => ['type' => 'int', 'default' => 30, 'label' => 'Sessiya timeout (dəq)'],
                'max_sessions' => ['type' => 'int', 'default' => 0, 'label' => 'Maks. eyni vaxtda sessiya (0=limitsiz)'],
                'max_login_attempts' => ['type' => 'int', 'default' => 5, 'label' => 'Maksimum login cəhdi'],
                'lockout_minutes' => ['type' => 'int', 'default' => 15, 'label' => 'Bloklama müddəti (dəq)'],
                'password_min_length' => ['type' => 'int', 'default' => 12, 'label' => 'Minimum parol uzunluğu'],
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
                'enable_email_otp' => ['type' => 'bool', 'default' => false, 'label' => 'Email OTP aktiv'],
                'tpl_welcome' => ['type' => 'text', 'default' => 'Salam Həyətimiz-ə xoş gəldiniz!', 'label' => 'Şablon — Xoş gəldiniz'],
                'html_enabled' => ['type' => 'bool', 'default' => true, 'label' => 'HTML email şablonları aktiv'],
                'subject_registration_otp' => ['type' => 'string', 'default' => 'Salam Həyətimiz — qeydiyyat təsdiq kodu', 'label' => 'Mövzu — Qeydiyyat OTP'],
                'subject_login_otp' => ['type' => 'string', 'default' => 'Salam Həyətimiz — giriş təsdiq kodu', 'label' => 'Mövzu — Giriş OTP'],
                'subject_welcome' => ['type' => 'string', 'default' => 'Salam Həyətimiz-ə xoş gəldiniz', 'label' => 'Mövzu — Xoş gəldiniz (reserved)'],
                'subject_password_set' => ['type' => 'string', 'default' => 'Salam Həyətimiz — parol təyin edildi', 'label' => 'Mövzu — Parol təyin (reserved)'],
                'subject_password_changed' => ['type' => 'string', 'default' => 'Salam Həyətimiz — parol dəyişdirildi', 'label' => 'Mövzu — Parol dəyişdi (reserved)'],
                'subject_email_changed' => ['type' => 'string', 'default' => 'Salam Həyətimiz — email dəyişdirildi', 'label' => 'Mövzu — Email dəyişdi (reserved)'],
                'subject_security_alert' => ['type' => 'string', 'default' => 'Salam Həyətimiz — təhlükəsizlik bildirişi', 'label' => 'Mövzu — Təhlükəsizlik (reserved)'],
            ],
            'otp' => [
                'otp_length' => ['type' => 'int', 'default' => 6, 'label' => 'OTP uzunluğu'],
                'otp_ttl_seconds' => ['type' => 'int', 'default' => 120, 'label' => 'OTP ömrü / bitmə (saniyə)'],
                'otp_resend_seconds' => ['type' => 'int', 'default' => 30, 'label' => 'Yenidən göndərmə intervalı (san)'],
                'otp_max_attempts' => ['type' => 'int', 'default' => 5, 'label' => 'Maksimum cəhd'],
                'otp_daily_limit' => ['type' => 'int', 'default' => 0, 'label' => 'Günlük limit (0=limitsiz)'],
                'otp_hourly_limit' => ['type' => 'int', 'default' => 0, 'label' => 'Saatlıq limit (0=limitsiz)'],
                'otp_temp_block_minutes' => ['type' => 'int', 'default' => 0, 'label' => 'Müvəqqəti blok müddəti (dəq, 0=yox)'],
                'enable_email_otp' => ['type' => 'bool', 'default' => false, 'label' => 'Email OTP'],
                'enable_sms_otp' => ['type' => 'bool', 'default' => true, 'label' => 'SMS OTP'],
                'brute_force_protection' => ['type' => 'bool', 'default' => true, 'label' => 'Brute-force qoruması'],
                'tpl_registration' => ['type' => 'text', 'default' => 'Qeydiyyat kodunuz: {code}', 'label' => 'Şablon — Qeydiyyat OTP'],
                'tpl_login' => ['type' => 'text', 'default' => 'Giriş kodunuz: {code}', 'label' => 'Şablon — Giriş OTP'],
                'tpl_password_reset' => ['type' => 'text', 'default' => 'Parol bərpa kodu: {code}', 'label' => 'Şablon — Parol bərpa OTP'],
            ],
            'payments' => [
                'kapital_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Kapital Bank aktiv'],
                'kapital_mode' => ['type' => 'select', 'default' => 'sandbox', 'label' => 'Rejim', 'options' => ['sandbox', 'production']],
                'kapital_api_base_url' => ['type' => 'string', 'default' => '', 'label' => 'API Base URL (boş = rejimə görə)'],
                'kapital_client_id' => ['type' => 'string', 'default' => '', 'label' => 'Client ID'],
                'kapital_client_secret' => ['type' => 'secret', 'default' => '', 'label' => 'Client Secret'],
                'kapital_scope' => ['type' => 'string', 'default' => 'email', 'label' => 'OAuth Scope'],
                'kapital_merchant_name' => ['type' => 'string', 'default' => '', 'label' => 'Merchant Name'],
                'kapital_merchant_id' => ['type' => 'string', 'default' => '', 'label' => 'Merchant ID'],
                'kapital_terminal_id' => ['type' => 'string', 'default' => '', 'label' => 'Terminal ID'],
                'kapital_webhook_secret' => ['type' => 'secret', 'default' => '', 'label' => 'Webhook Secret (X-Signature)'],
                'kapital_ip_allowlist' => ['type' => 'string', 'default' => '', 'label' => 'Webhook IP allowlist (vergüllə)'],
                'kapital_return_url' => ['type' => 'string', 'default' => '', 'label' => 'Return URL'],
                'kapital_currency' => ['type' => 'string', 'default' => 'AZN', 'label' => 'Valyuta'],
                'kapital_timeout_seconds' => ['type' => 'int', 'default' => 15, 'label' => 'HTTP timeout (san)'],
                'kapital_connect_timeout_seconds' => ['type' => 'int', 'default' => 5, 'label' => 'Connect timeout (san)'],
                'kapital_retries' => ['type' => 'int', 'default' => 3, 'label' => 'Təkrar sayı'],
                'kapital_callback_timeout_minutes' => ['type' => 'int', 'default' => 30, 'label' => 'Sifariş gözləmə (dəq)'],
                'kapital_authorising_recheck_minutes' => ['type' => 'int', 'default' => 30, 'label' => 'Recheck pəncərəsi (dəq)'],
                'kapital_refund_window_days' => ['type' => 'int', 'default' => 365, 'label' => 'Refund pəncərəsi (gün)'],
                'enable_logging' => ['type' => 'bool', 'default' => true, 'label' => 'Loqlama aktiv'],
                'enable_webhook_logging' => ['type' => 'bool', 'default' => true, 'label' => 'Webhook loqlama aktiv'],
                'save_card_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Kart yadda saxlama (V1.3-də yoxdur)'],
                'recurring_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Təkrarlanan ödəniş (V1.3-də yoxdur)'],
            ],
            'traccar' => [
                'host' => ['type' => 'string', 'default' => '', 'label' => 'Host'],
                'api_url' => ['type' => 'string', 'default' => 'http://127.0.0.1:8082', 'label' => 'API URL'],
                'username' => ['type' => 'string', 'default' => '', 'label' => 'İstifadəçi'],
                'password' => ['type' => 'secret', 'default' => '', 'label' => 'Parol'],
                'api_token' => ['type' => 'secret', 'default' => '', 'label' => 'API Token'],
                'webhook_token' => ['type' => 'secret', 'default' => '', 'label' => 'Webhook Token'],
                'gps_host' => ['type' => 'string', 'default' => 'gps.salamheyetimiz.com', 'label' => 'GPS Host'],
                'gps_port' => ['type' => 'int', 'default' => 5011, 'label' => 'GPS Port'],
                'offline_threshold_minutes' => ['type' => 'int', 'default' => 15, 'label' => 'Offline həddi (dəq)'],
                'command_timeout_seconds' => ['type' => 'int', 'default' => 10, 'label' => 'Əmr timeout (san)'],
                'heartbeat_timeout_seconds' => ['type' => 'int', 'default' => 120, 'label' => 'Heartbeat timeout (san)'],
                'retry_count' => ['type' => 'int', 'default' => 3, 'label' => 'Təkrar sayı'],
                'retry_delay_seconds' => ['type' => 'int', 'default' => 5, 'label' => 'Təkrar gecikməsi (san)'],
                'enable_webhook' => ['type' => 'bool', 'default' => true, 'label' => 'Webhook aktiv'],
                'enable_commands' => ['type' => 'bool', 'default' => true, 'label' => 'Əmrlər aktiv'],
                'enable_diagnostics' => ['type' => 'bool', 'default' => true, 'label' => 'Diaqnostika aktiv'],
                'enable_auto_sync' => ['type' => 'bool', 'default' => false, 'label' => 'Avto-sync aktiv'],
                'enable_heartbeat' => ['type' => 'bool', 'default' => false, 'label' => 'Heartbeat aktiv'],
            ],
            'sms' => [
                'provider' => ['type' => 'select', 'default' => 'fake', 'label' => 'Əsas provayder (Primary)', 'options' => ['fake', 'lsim', 'infobip', 'twilio']],
                'secondary_provider' => ['type' => 'select', 'default' => '', 'label' => 'İkinci provayder (Secondary)', 'options' => ['', 'lsim', 'infobip', 'twilio']],
                'base_url' => ['type' => 'string', 'default' => '', 'label' => 'Base URL'],
                'api_key' => ['type' => 'secret', 'default' => '', 'label' => 'API açarı'],
                'sender_id' => ['type' => 'string', 'default' => 'SalamHayet', 'label' => 'Sender ID'],
                'sms_fallback_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'SMS fallback aktiv'],
                'email_fallback_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Email fallback aktiv'],
                'fallback_provider' => ['type' => 'select', 'default' => '', 'label' => 'Fallback provayder', 'options' => ['', 'lsim', 'infobip', 'twilio']],
            ],
            'subscriptions' => [
                'default_trial_days' => ['type' => 'int', 'default' => 0, 'label' => 'Default trial (gün)'],
                'subscription_duration_days' => ['type' => 'int', 'default' => 365, 'label' => 'Abunəlik müddəti (gün)'],
                'grace_period_days' => ['type' => 'int', 'default' => 7, 'label' => 'Güzəşt müddəti (gün)'],
                'auto_renew_enabled' => ['type' => 'bool', 'default' => false, 'label' => 'Avtomatik yenilənmə'],
                'renewal_reminder_days' => ['type' => 'int', 'default' => 7, 'label' => 'Yenilənmə xatırlatması (gün öncə)'],
                'expiration_reminder_days' => ['type' => 'int', 'default' => 3, 'label' => 'Bitmə xatırlatması (gün öncə)'],
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
