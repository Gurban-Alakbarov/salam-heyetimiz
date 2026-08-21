# Settings v2 — Test Report

Date: 2026-06-28. Scope: production-completion of the Settings module (no Kapital, no SMS provider).

## 1. Automated tests (local, MariaDB `salam_testing`)

Full suite: **283 passed / 1034 assertions / 0 failures** (`vendor/bin/pest`). No regressions from the
OtpService / JwtRequestGuard / middleware / SettingsService changes (the auth + payments + subscriptions +
device suites all stayed green).

### `tests/Feature/Settings/SettingsV2Test.php` (9)

| Test | Asserts |
|---|---|
| records a rich audit entry (old→new + ip) and a version snapshot on update | `settings.updated` payload carries `changes[]` with old/new; `ip` set; a `settings_versions` row (reason=update). |
| redacts secrets in the audit diff | secret change logged as `••••••`, never the plaintext. |
| exports and re-imports settings as JSON | export `settings` has `general.app_name`; import applies + writes a version. |
| lists, compares and restores a previous version | restore brings `app_name` back to the older value; compare diff includes the key. |
| overlays runtime settings onto config via the bridge | `config('domain.auth.access.admin.ttl_seconds')` + `otp.length` reflect DB settings. |
| enforces the OTP hourly limit when configured | 3rd issue in an hour throws `OtpThrottleException` (limit 2). |
| force-logout invalidates admin tokens issued before the cutoff (guard) | guard returns null for a token older than `force_logout_at`. |
| force-logout endpoint revokes user refresh tokens and is super-only | endpoint returns `user_tokens_revoked`; finance → 403. |
| serves honest test actions and live traccar status | email/sms test → `ok:false` (no fake); traccar status structure; support → 403. |

### Existing suites preserved

`SettingsModuleTest` (5) + `Admin2faToggleTest` (3) + the full Auth suite (OTP issue/verify, admin login,
2FA) — all green with the new OtpThrottle (default unlimited → no-op) and config bridge in place.

## 2. Live verification (production — api.salamheyetimiz.com)

Performed with the super-admin token over HTTPS. Results:

| Check | Result |
|---|---|
| New catalog fields | `security.max_sessions=0`, `email.from_name`, `otp.tpl_login`, `otp.otp_daily_limit`, `payments.success_url` field, `sms.secondary_provider`, `traccar.heartbeat_timeout_seconds=120` — all present. |
| Update + rich audit | PATCH `otp.otp_daily_limit 0→5` → audit `settings.updated` with `ip=127.0.0.1`, `user_agent` present, `changes=[{key:otp_daily_limit, old:"0", new:"5"}]`. |
| Version history | version #1 (reason=update, scope_group=otp, actor=super@…, ip) created; compare endpoint OK. |
| Export | `_meta.key_count=90`, `settings` includes `general.app_name`. |
| Import | `{ok:true, applied:1}`; `otp_daily_limit` reset to 0. |
| Traccar live status | `connection=connected`, `version=6.14.5`, `device_count=1`, `last_webhook=2026-06-27 13:37:56` (real), `last_command/ack/sync=null` (no commands issued yet — real, not faked). |
| Test email (unconfigured) | `ok:false` — "SMTP host və From Email təyin edilməlidir." (no fake success). |
| Test SMS (no provider) | `ok:false` — honest "provider not integrated, SMS is fallback". |
| Permission gating | finance → **403** on export, versions, force-logout (permission checked before any side effect). |

### Force Logout All — deliberately not triggered on production

The global force-logout sets `force_logout_at=now` (invalidating every live admin token) **and revokes every
user refresh token** (forcing mobile re-login). Triggering it as a "test" would disrupt live sessions, so on
production only the **gating** was verified (finance → 403, which short-circuits before any side effect). The
mechanism itself is proven by two local tests: the guard rejects pre-cutoff tokens, and the endpoint revokes
refresh tokens. It is safe to use in production when an operator actually intends a global logout.

## 3. Deployment

`composer dump-autoload` (6572 classes), `migrate --force` (settings_versions created), `db:seed SettingsSeeder`
(new defaults), `route:cache`, `php8.4-fpm reload` + `salam-horizon restart` — all green; both services active.
Admin SPA dist redeployed to `/var/www/salam-admin/dist` (previous build backed up).

## 4. Outcome

Production-ready, no placeholders/TODOs/mocks. All existing functionality, routes, and permissions preserved.
Ready for Kapital + SMS integrations to be added on top of the prepared settings.
