# Settings v2 — Architecture (production-complete)

Status: implemented (v1.4). Builds on v1 (`SETTINGS_ARCHITECTURE.md`) and makes the module
production-complete **before** any external integration (no Kapital, no SMS provider wired). Everything is
DB-driven, cached, encrypted, audited, versioned, and live-tunable with no deploy.

## What v2 adds over v1

| Capability | How |
|---|---|
| Live tunables (JWT/refresh/lockout/OTP) | `SettingsConfigBridge` + `ApplyRuntimeSettings` middleware overlay DB settings onto Laravel `config` per-request — existing services pick them up unchanged. |
| Rich audit | Every change records per-key **old→new** (secrets redacted) + actor + IP + User-Agent (auto-captured by `AuditLogger`). Surfaced in the Audit Log (expandable rows). |
| Version history | `settings_versions` table — immutable snapshot per mutation; **View History / Compare / Restore**. |
| Import / Export | JSON snapshot for disaster recovery + server migration. |
| Real test actions | Send Test Email, Send Test OTP (real SMTP from settings), SMTP test, Traccar ping + live status, Payments probe. SMS test is honest (no provider → clear message). |
| Force Logout All | `force_logout_at` cutoff + JWT `iat` check in the admin guard, plus revoke all user refresh tokens. |
| OTP throttle | Daily/hourly limit + temporary block (default 0 = unlimited) enforced at issuance. |
| Password policy / max sessions / heartbeat / merchant + URLs / from/reply-to | New catalog fields (stored, used by the live paths / ready for integration). |

## Components (new in v2)

| Layer | File | Responsibility |
|---|---|---|
| Config bridge | `app/Domain/Admin/Settings/SettingsConfigBridge.php` | Maps catalog → Laravel config keys; overlays positive ints. |
| Middleware | `app/Http/Middleware/ApplyRuntimeSettings.php` | Calls the bridge per request on the `api` group; fail-open. Registered in `bootstrap/app.php`. |
| Mailer | `app/Domain/Admin/Settings/RuntimeMailer.php` | Builds a Symfony SMTP transport from Email settings and sends (no `config/mail.php`). |
| Versions | `app/Domain/Admin/Services/SettingsVersionService.php` + `Models/SettingsVersion.php` + migration `09_settings/..._create_settings_versions_table.php` | Snapshot / list / compare / restore. |
| OTP throttle | `app/Domain/Auth/Services/OtpThrottle.php` + `Exceptions/OtpThrottleException.php` | Per-phone daily/hourly cap + temp block; wired into `OtpService::issue`. |
| Force logout | `JwtService::parseAdminClaims` exposes `issued_at`; `JwtRequestGuard` rejects admin tokens older than `security.force_logout_at`. |
| Controllers | `SettingsController` (CRUD + export/import + versions), `SettingsTestController` (tests/ops/force-logout) | All gated `system.settings.manage`. |

## The config bridge (why services didn't change)

The bridge rewrites the exact config keys the services already read:

```
security.jwt_user_ttl_seconds   → domain.auth.access.user.ttl_seconds
security.jwt_admin_ttl_seconds  → domain.auth.access.admin.ttl_seconds
security.refresh_ttl_days       → domain.auth.refresh.ttl_days
security.max_login_attempts     → domain.auth.admin_login.max_failed
security.lockout_minutes        → domain.auth.admin_login.lockout_minutes
otp.otp_ttl_seconds / length / max_attempts / resend_seconds → domain.auth.otp.*
```

`JwtService`, `OtpService`, `AdminAuthService` are untouched — they keep reading `config(...)`, which is now
runtime-overridden. Only positive ints overlay, so an unset/zero value leaves the file default intact.

## Force Logout All Sessions

1. The endpoint stamps `security.force_logout_at = now()` (unix) and revokes every active row in
   `refresh_tokens` (`revocation_reason = admin`).
2. `JwtRequestGuard::resolveAdmin` rejects any admin token whose `iat < force_logout_at`. Admin tokens are
   short-lived (≤30 min) and stateless, so this is the global kill-switch; users are killed via refresh-token
   revocation. The actor is logged out too (re-login required) — by design.

## Audit (everything appears in Audit)

`settings.updated` carries `payload.changes = [{key, old, new}]` (secrets shown as `••••••`). `AuditLogger`
auto-captures actor + IP + User-Agent + timestamp. The Audit Log page renders an expandable detail row with
the old→new diff and the User-Agent. Other recorded actions: `settings.exported`, `settings.imported`,
`settings.restored`, `settings.force_logout`, `settings.email_test_sent`, `settings.otp_test_sent`.

## Import / Export

`GET /admin/v1/settings/export` → `{_meta, settings}` (catalog-complete; secrets stay AES ciphertext, portable
only on a server with the same `APP_KEY`). `POST /admin/v1/settings/import {settings}` upserts known catalog
keys and writes a version. Cross-`APP_KEY` migrations re-enter secrets.

## Permissions

Unchanged: the entire module is `system.settings.manage` (super_admin only). See `SETTINGS_PERMISSION_MATRIX.md`.

## What is deliberately NOT done (per the task)

- No Kapital API code — only the Payments settings + Test Connection probe.
- No SMS provider — only the settings + an honest "not integrated" test response. SMS remains a **fallback**
  transport, not primary.
