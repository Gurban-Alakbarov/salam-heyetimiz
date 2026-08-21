# Settings — Changelog

## v2 (2026-06-28) — production-complete

Made the Settings module production-complete before external integrations. No existing functionality removed;
all v1 keys, routes and permissions preserved. No Kapital, no SMS provider integrated.

### Added — catalog fields

- **Security**: `max_sessions`, `password_require_uppercase`, `password_require_number`, `password_require_symbol`.
- **Email**: `from_name`, `from_email`, `reply_to_email` (replacing the v1 `sender_name`/`sender_email` labels).
- **OTP**: `otp_daily_limit`, `otp_hourly_limit`, `otp_temp_block_minutes`; templates `tpl_registration`,
  `tpl_login`, `tpl_password_reset` (separate Registration / Login / Password-Reset OTP).
- **Payments**: `kapital_merchant_name`, `success_url`, `fail_url`, `cancel_url`, `enable_logging`,
  `enable_webhook_logging`, `save_card_enabled`, `recurring_enabled`.
- **Traccar**: `heartbeat_timeout_seconds`.
- **SMS**: `secondary_provider`, `sms_fallback_enabled`, `email_fallback_enabled`.

### Added — behaviour

- **Live tunables**: `SettingsConfigBridge` + `ApplyRuntimeSettings` middleware overlay JWT/refresh/lockout/OTP
  settings onto config every request → existing services honour admin edits with no code change.
- **Rich audit**: per-key old→new diff (secrets redacted) + actor + IP + User-Agent on every change; surfaced
  as expandable rows in the Audit Log.
- **Version history**: `settings_versions` table; View History / Compare / Restore.
- **Import / Export**: JSON snapshot endpoints for DR + server migration.
- **Real test actions**: Send Test Email, Send Test OTP (runtime SMTP), SMTP connect test, Traccar ping +
  live status (last webhook/command/ACK/device-sync from real DB), Payments probe. SMS test returns an honest
  "no provider integrated" message.
- **Force Logout All Sessions**: `force_logout_at` cutoff + JWT `iat` check + revoke all user refresh tokens.
- **OTP throttle**: daily/hourly limit + temporary block (default 0 = unlimited), enforced at issuance.

### API — new endpoints (all `system.settings.manage`)

```
GET    /admin/v1/settings/export
POST   /admin/v1/settings/import
GET    /admin/v1/settings/versions
GET    /admin/v1/settings/versions/compare?from=&to=
POST   /admin/v1/settings/versions/{id}/restore
POST   /admin/v1/settings/email/send-test
POST   /admin/v1/settings/email/send-test-otp
POST   /admin/v1/settings/sms/send-test
POST   /admin/v1/settings/sms/send-test-otp
GET    /admin/v1/settings/traccar/status
POST   /admin/v1/settings/security/force-logout
```

`PATCH /admin/v1/settings/{group}` and `POST /admin/v1/settings/{group}/test` retained (test moved to
`SettingsTestController`). `GET /admin/v1/audit` now also returns `user_agent`.

### Migrations

- `09_settings/2026_06_27_090002_create_settings_versions_table.php`.

### Files

New: `SettingsConfigBridge`, `RuntimeMailer`, `SettingsVersionService`, `SettingsVersion`, `OtpThrottle`,
`OtpThrottleException`, `ApplyRuntimeSettings`, `SettingsTestController`. Modified: `SettingsCatalog`,
`SettingsService`, `SettingsController`, `OtpService`, `JwtService`, `JwtRequestGuard`, `AuditController`,
`bootstrap/app.php`, `routes/admin.php`, `SettingsSeeder` (via catalog).

### Tests

`tests/Feature/Settings/SettingsV2Test.php` (9). Full suite **283 passed / 1034 assertions** — no regressions.

## v1 (2026-06-28) — initial module

DB-driven grouped catalog (8 groups), cached `SettingsService`, encrypted secrets, generic UI, System health,
menu reorg, Traccar tab. See `SETTINGS_ARCHITECTURE.md` / `SETTINGS_DATABASE.md`.
