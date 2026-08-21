# System Settings — Database / Catalog

The catalog (`app/Domain/Admin/Settings/SettingsCatalog.php`) is the source of truth. Each row below is one
setting; the storage key is `{group}.{key}` in the `settings` table. Secrets are stored **encrypted** and
never returned by the API. Defaults are seeded idempotently (`SettingsSeeder` → `SettingsService::seedDefaults`),
reusing existing `config/*` values.

## `settings` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `key` | string, unique | `{group}.{key}` |
| `value` | text, nullable | coerced string; secrets are ciphertext |
| `updated_by_admin_id` | bigint FK, nullable | last editor (audited) |
| `created_at` / `updated_at` | timestamps | |

## General (`general`)
| Key | Type | Default |
|---|---|---|
| app_name | string | Salam Həyətimiz |
| timezone | string | Asia/Baku |
| default_language | select(az,ru,en) | az |
| maintenance_mode | bool | false |
| logo_url | string | "" |
| favicon_url | string | "" |

## Security (`security`)
| Key | Type | Default | Source |
|---|---|---|---|
| require_2fa | bool | false | existing `security.require_2fa` toggle |
| jwt_user_ttl_seconds | int | 900 | config/domain/auth access.user |
| jwt_admin_ttl_seconds | int | 1800 | config/domain/auth access.admin |
| refresh_ttl_days | int | 60 | config/domain/auth refresh |
| session_timeout_minutes | int | 30 | new |
| max_login_attempts | int | 5 | config/domain/auth admin_login.max_failed |
| lockout_minutes | int | 15 | config/domain/auth admin_login.lockout_minutes |
| password_min_length | int | 12 | new |

## Email (`email`)
| Key | Type | Default |
|---|---|---|
| smtp_host | string | "" |
| smtp_port | int | 587 |
| smtp_username | string | "" |
| smtp_password | **secret** | "" |
| smtp_encryption | select(none,tls,ssl) | tls |
| sender_name | string | Salam Həyətimiz |
| sender_email | string | "" |
| enable_email_otp | bool | false |
| tpl_registration_otp | text | "Qeydiyyat kodunuz: {code}" |
| tpl_password_reset | text | "Parol bərpa kodu: {code}" |
| tpl_welcome | text | "Salam Həyətimiz-ə xoş gəldiniz!" |

## OTP (`otp`)
| Key | Type | Default | Source |
|---|---|---|---|
| otp_length | int | 6 | config/domain/auth otp.length |
| otp_ttl_seconds | int | 120 | config/domain/auth otp.ttl_seconds |
| otp_max_attempts | int | 5 | config/domain/auth otp.max_attempts |
| otp_resend_seconds | int | 30 | config/domain/auth otp.resend_seconds |
| enable_email_otp | bool | false | new |
| enable_sms_otp | bool | true | new |
| brute_force_protection | bool | true | new |

## Payments (`payments`) — provider-agnostic, currently Kapital
| Key | Type | Default |
|---|---|---|
| kapital_enabled | bool | false |
| kapital_mode | select(sandbox,production) | sandbox |
| kapital_merchant_id | string | "" |
| kapital_terminal_id | string | "" |
| kapital_client_id | string | "" |
| kapital_client_secret | **secret** | "" |
| kapital_webhook_secret | **secret** | "" |
| kapital_api_base_url | string | "" |
| kapital_checkout_url | string | "" |

Read-only (computed, returned in `readonly.payments`): `webhook_url`, `callback_url` = `/v1/payments/callback`.

## Traccar (`traccar`)
| Key | Type | Default | Source |
|---|---|---|---|
| host | string | "" | new |
| api_url | string | http://127.0.0.1:8082 | config/integrations/traccar base_url |
| username | string | "" | new |
| password | **secret** | "" | new |
| api_token | **secret** | "" | config/integrations/traccar api_token |
| webhook_token | **secret** | "" | config/integrations/traccar forward_token |
| gps_host | string | gps.salamheyetimiz.com | new |
| gps_port | int | 5011 | new |
| offline_threshold_minutes | int | 15 | config/domain/devices |
| command_timeout_seconds | int | 10 | config/integrations/traccar timeout |
| retry_count | int | 3 | new |
| retry_delay_seconds | int | 5 | new |
| enable_webhook | bool | true | new |
| enable_commands | bool | true | new |
| enable_diagnostics | bool | true | new |
| enable_auto_sync | bool | false | new |
| enable_heartbeat | bool | false | new |

Read-only (computed, `readonly.traccar`): `webhook_url` = `/v1/traccar/forward`.

## SMS (`sms`) — provider-agnostic, prepared (no provider wired yet)
| Key | Type | Default |
|---|---|---|
| provider | select(fake,lsim,infobip,twilio) | fake |
| base_url | string | "" |
| api_key | **secret** | "" |
| sender_id | string | SalamHayet |
| fallback_provider | select("",lsim,infobip,twilio) | "" |

## Subscriptions (`subscriptions`)
| Key | Type | Default |
|---|---|---|
| default_trial_days | int | 0 |
| subscription_duration_days | int | 365 |
| grace_period_days | int | 7 |
| auto_renew_enabled | bool | false |
| renewal_reminder_days | int | 7 |
| expiration_reminder_days | int | 3 |

## System (`system`) — read-only health, no stored settings
Served by `GET /admin/v1/system/health`: app (Laravel/PHP/env), database, redis, queue (driver + failed),
horizon, disk, memory, cpu, smtp, payment, cloudflare, traccar (status/version/device_count/queue/last_webhook).
