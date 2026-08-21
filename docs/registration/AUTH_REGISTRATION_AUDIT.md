# AUTH & REGISTRATION — AS-IS AUDIT

> Status: **audit only** — describes the system exactly as it exists today (no proposals here; design lives in the sibling docs). Verified by reading the source on 2026-06-29.
> Decision baseline (confirmed by product owner): the platform stays **passwordless / OTP-based**, but the registration + primary login channel moves from **phone/SMS-OTP → EMAIL-OTP**. A password is **not** collected at registration; it may be set later, voluntarily, from a personal-cabinet "Security" section. SMS infrastructure is **not** touched.

---

## 0. Executive summary

| Area | Current state | Relevant to registration |
|---|---|---|
| Mobile auth model | **Passwordless, phone-OTP, auto-provisioning** (user row is created on first OTP verify) | Pivot to email-OTP; keep passwordless |
| Password | **No `password` column on `users`** | Registration adds none; optional later |
| OTP storage | `otps` table keyed by **`phone`** (NOT NULL); purposes `login`/`recover`/`email_verify` | `email_verify` enum exists but **dormant**; needs an email channel |
| OTP delivery | `OtpTransport` → **SMS** (or fake in tests) | Needs an **email** transport |
| Email/SMTP | `RuntimeMailer` — **production-ready SMTP** (Brevo, domain verified, SPF/DKIM active, delivery confirmed); **plain-text body only** today | Reuse transport; add generic HTML templates |
| HTML email template | **None exists** | Must be designed |
| Password reset | **Dormant** (`password_reset_tokens` table unused; no mobile flow) | Out of scope (no password yet) |
| JWT + Refresh | RS256 access (15 min) + opaque rotating refresh (60 d) | **Reuse as-is** |
| Settings (OTP/email) | Full catalog incl. `otp.*` + `email.*` + 3 text templates | **Reuse** for TTL/resend/templates |
| RBAC | **Admin-only**; mobile users have no roles | Registration grants no roles |
| Audit | Event-driven (`AuditableEvent`) | Reuse for register/verify events |

**Key gap:** the OTP engine is phone/SMS-shaped; everything else (User model, JWT, refresh, Settings, audit, mailer transport) is reusable with additive extension. There is **no registration endpoint** and **no profile-update endpoint** today.

---

## 1. Mobile API authentication (existing)

**Controller:** `app/Http/Api/V1/Controllers/Auth/AuthController.php`
**Routes:** `routes/api.php` (prefix `/v1`)

| Method | Route | Guard | Throttle | Purpose |
|---|---|---|---|---|
| `requestOtp` | `POST /v1/auth/otp/request` | public | `throttle:otp-request` | Send phone OTP (202) |
| `verifyOtp` | `POST /v1/auth/otp/verify` | public | `throttle:otp-verify` | Verify OTP → issue tokens (200) |
| `refresh` | `POST /v1/auth/refresh` | public | `throttle:public` | Rotate tokens (200) |
| `logout` | `POST /v1/auth/logout` | `auth:user` | `throttle:mobile` | Revoke device tokens (204) |

**`requestOtp`** → `RequestOtpRequest` rules: `phone` `regex:/^\+994\d{9}$/`, `purpose` `in:login,recover` (default `login`). Returns `{expires_in_seconds, resend_available_in_seconds}`.

**`verifyOtp`** → `VerifyOtpRequest` rules: `phone`, `code` `regex:/^\d{6}$/`, `device.install_uuid` (uuid), `device.platform` (`ios|android`), optional `os_version/app_version/device_model/push_token`. Calls `VerifyOtpAndIssueTokens`. Success body (`authSuccess()` → `UserSelfResource`):
```json
{ "access_token","refresh_token","token_type":"Bearer","expires_in":900,
  "refresh_expires_in":5184000,
  "user":{ "id","phone","full_name","email","email_verified_at","preferred_language","status","created_at","last_login_at","has_active_subscription" } }
```

**Auto-provisioning (critical):** `VerifyOtpAndIssueTokens::handle()` runs `User::firstOrCreate(['phone'=>$phone],[ 'phone_country'=>'AZ','preferred_language'=>'az','status'=>Active ])`, then blocks issue if status ≠ Active, registers the `UserDevice`, issues refresh + access, dispatches `UserAuthenticated`. **There is no separate "register" step today.**

---

## 2. Admin authentication (existing — for completeness)

**Controller:** `app/Http/Admin/V1/Controllers/Auth/AdminAuthController.php` · **Service:** `app/Domain/Auth/Services/AdminAuthService.php`

- `POST /admin/v1/auth/login` — email + bcrypt **password** (admins DO have passwords), 2-phase. Lockout via `admin_users.failed_login_count` + `locked_until` (thresholds from `security.max_login_attempts` / `lockout_minutes`). If `security.require_2fa` OFF → returns access token directly; else returns `challenge_token`.
- `POST /admin/v1/auth/2fa/verify` — TOTP (6-digit) or recovery code (10 hex), single-use recovery codes (`admin_users.recovery_codes_hashes`), issues admin JWT with `tfa_verified`.

> Admin auth is a **separate identity space** and is **out of scope** for registration; documented only to confirm it is unaffected.

---

## 3. JWT (existing — reuse as-is)

**Service:** `app/Domain/Auth/Services/JwtService.php` · **Config:** `config/domain/auth.php` · **Keys:** `app/Domain/Auth/Support/JwtKeyRing.php`

- Algorithm **RS256** (lcobucci/jwt). Issuer `salam-hayetimiz`.
- **User access token:** `issueUserAccessToken(userId, fingerprint)` → claims `iss`, `aud=salam-mobile`, `sub=user.{id}`, `iat`, `exp` (now+900s), `jti`, `kind=user`, `fp` (install_uuid); header `kid=user-2026-q2`. TTL `domain.auth.access.user.ttl_seconds` = **900s**.
- Keys resolved from env or `storage/keys/jwt_user_*.pem` (`auth:generate-keys`).
- **Guard:** `app/Domain/Auth/Guards/JwtRequestGuard.php` (`viaRequest('salam-jwt-user')`, config `auth.guards.user`). Validates signature + `iss`/`aud`/`exp`/`kind`, loads `User`, requires status `Active`, stores `fp` in request attributes.

---

## 4. Refresh token (existing — reuse as-is)

**Model:** `app/Domain/Auth/Models/RefreshToken.php` · **Service:** `app/Domain/Auth/Services/RefreshTokenService.php` · **Table:** `refresh_tokens`

- Columns: `user_id`, `user_device_id`, `token_hash` (CHAR(64) unique, SHA-256), `issued_at`, `last_used_at`, `expires_at`, `revoked_at`, `revocation_reason` (enum `rotated|logout|password_change|admin|security|expired`), `replaced_by_id`, `ip`, `user_agent`.
- Plaintext = 32 random bytes → base64url; **60-day** TTL (`domain.auth.refresh.ttl_days`).
- **Rotation** (`rotate()`): replay of a revoked token → revokes the whole device family (`Security`) + `RefreshTokenReuseDetected`; expired → revoked; valid → new token, old marked `rotated` + `replaced_by_id`.
- **Logout** (`LogoutUser`): resolves the calling install by `fp`, revokes active tokens for that `(user, device)` with reason `Logout`.

---

## 5. OTP system (existing — extend for email)

**Service:** `app/Domain/Auth/Services/OtpService.php` · **Model:** `app/Domain/Auth/Models/Otp.php` · **Table:** `otps`

**`otps` columns:** `id`, `phone` VARCHAR(20) **NOT NULL**, `code_hash` CHAR(64) (SHA-256, hidden), `purpose` ENUM(`login`,`recover`,`email_verify`), `attempts` TINYINT, `max_attempts` TINYINT, `expires_at`, `consumed_at` (nullable), `issued_ip`, `created_at`. Indexes: `idx_otps_phone_purpose (phone,purpose)`, `idx_otps_expires_at`.

**`OtpService::issue(phone, OtpPurpose, ip, locale)`** — generates a numeric code of `domain.auth.otp.length` (6) via `random_int` + zero-pad; SHA-256 at rest; supersedes prior unconsumed `(phone,purpose)` codes; sets `expires_at = now + otp.ttl_seconds` (120); calls `OtpTransport::send()`; returns `{expires_in_seconds, resend_available_in_seconds}`.

**`OtpService::verify(phone, code, ?OtpPurpose)`** — `DB::transaction` + `lockForUpdate`; `hash_equals` compare; outcomes throw `OtpVerificationException::wrongCode()/expired()/maxAttempts()` (all 401); on success sets `consumed_at` (single use); increments `attempts` on miss.

**Purposes (`app/Domain/Auth/Enums/OtpPurpose.php`):** `Login` (active), `Recover` (validated in `RequestOtpRequest` but never consumed), **`EmailVerify` (defined, never used anywhere)**.

**Transport (`app/Domain/Auth/Contracts/OtpTransport.php`):** `send(phone, code, locale)`. Impls: `SmsOtpTransport` (HTTP to SMS provider, config `integrations.sms.*`), `FakeOtpTransport` (test). **No email transport.**

**Throttle (`app/Domain/Auth/Services/OtpThrottle.php`):** cache-based per-phone hourly/daily/temp-block from `otp.otp_hourly_limit` / `otp_daily_limit` / `otp_temp_block_minutes` (all default **0 = unlimited / no-op**).

**HTTP limiters (`app/Providers/RouteServiceProvider.php`):**
- `otp-request`: `3 / phone / 10 min` **AND** `30 / IP / hour`.
- `otp-verify`: `10 / phone / 10 min`.

---

## 6. Email sending & SMTP (existing — reuse transport, add HTML)

**`app/Domain/Admin/Settings/RuntimeMailer.php`** — builds a **Symfony `EsmtpTransport`** at runtime from `email.*` settings (no `config/mail.php`). Signature **`send(string $to, string $subject, string $body): void`** — sets From (`email.from_email`/`from_name`), optional Reply-To, body **as plain text only** (`->text($body)`, **not HTML**). `isConfigured()` = host + from_email present.

> **Production-ready (2026-06-29):** the SMTP transport is live on **Brevo** with domain verification complete and **SPF + DKIM active**; runtime SMTP Settings are in effect; **Test Email + Test OTP already deliver successfully to real inboxes (Gmail confirmed)**. The only remaining gaps are the *body format* (plain-text) and the *absence of templates* — both closed by the generic email template system in `USER_REGISTRATION_ARCHITECTURE.md §7`. There is **no SMTP/deliverability blocker**.

- **No Mailable classes, no Notification classes, no Blade email views** anywhere.
- `SettingsTestController::sendTestEmail` → hardcoded AZ plain-text. `sendTestOtp` → generates a code, substitutes `{code}` into the `otp.tpl_{template}` setting via `str_replace`, sends plain text. Used for admin testing only — **not wired into any user flow**.
- **No professional HTML email template exists.**

---

## 7. Settings — OTP & Email parameters (existing — reuse, no hardcode)

**`app/Domain/Admin/Settings/SettingsCatalog.php`** (group → key → type → default):

**`otp` group:** `otp_length`(int=6), `otp_ttl_seconds`(int=120), `otp_resend_seconds`(int=30), `otp_max_attempts`(int=5), `otp_daily_limit`(int=0), `otp_hourly_limit`(int=0), `otp_temp_block_minutes`(int=0), `enable_email_otp`(bool=false), `enable_sms_otp`(bool=true), `brute_force_protection`(bool=true), `tpl_registration`(text="Qeydiyyat kodunuz: {code}"), `tpl_login`(text="Giriş kodunuz: {code}"), `tpl_password_reset`(text="Parol bərpa kodu: {code}").

**`email` group:** `smtp_host`, `smtp_port`(587), `smtp_username`, `smtp_password`(secret), `smtp_encryption`(select none/tls/ssl=tls), `from_name`, `from_email`, `reply_to_email`, `enable_email_otp`(bool=false).

> Live config bridge (`SettingsConfigBridge` + `ApplyRuntimeSettings` middleware) overlays these onto `config('domain.auth.otp.*')` at request time, so reading TTL/resend from config already honours Settings. **`enable_email_otp` already exists** — the switch is in place but unused.

---

## 8. Data models (existing)

**`User`** (`app/Domain/Users/Models/User.php`, table `users`): `id`, `phone` VARCHAR(20) **UNIQUE** (`uq_users_phone`), `phone_country`, `full_name` VARCHAR(120) nullable, `email` VARCHAR(160) **UNIQUE** nullable (`uq_users_email`), `email_verified_at` nullable, `preferred_language` enum(az/ru/en), `status` enum(**active**/blocked/self_deleted), `blocked_reason`, `blocked_by_admin_id`, `last_login_at`, `last_login_ip`, timestamps, `deleted_at` (soft). **No `password`, no `phone_verified_at`.** `$guarded=['id']`. Casts: `status`→UserStatus, `preferred_language`→Locale, `email_verified_at`/`last_login_at`→datetime. Relations: `userDevices`, `deviceUsers`, `ownedDevices` (via `devices.owner_user_id`), `consents`.

**"Resident"** = **not a model**; it is a `User` with an active `device_users` roster row on a `Device` belonging to a `Complex` (read model `app/Domain/Roster/Queries/ResidentQuery.php`).

**"Device owner"** = **not a model**; `devices.owner_user_id` FK + a `device_users.role` enum(`owner`/`user`).

**`Subscription`** (`subscriptions`): keyed by `device_user_id` (UNIQUE), status enum `pending_payment|active|expired|cancelled|refunded` (default `pending_payment`). **Created only by the payment flow, never at registration.**

**`Complex`** (`complexes`): users link to a complex **indirectly** (User → DeviceUser → Device → Complex). No `users.complex_id`.

**User activation today:** the row is created with `status='active'` immediately on first OTP verify; there is **no explicit activation step, no `activated_at`**. `email_verified_at` is the only verification timestamp and is currently **never populated** by any flow.

---

## 9. Events, listeners, audit, RBAC

- **Events** (`app/Domain/Auth/Events/`): `UserAuthenticated`, `OtpRequested`, `UserLoggedOut`, `RefreshTokenReuseDetected`, `AdminAuthenticated`, `AdminLoginFailed`, `AdminLoggedOut`, `RecoveryCodesRegenerated` — all implement `AuditableEvent`.
- **Listeners** (wired in `app/Domain/Auth/ModuleServiceProvider.php`): `RecordSuccessfulUserLogin` (updates `users.last_login_at/ip`, `user_devices.last_seen_*`), `RecordAdminLogin`.
- **Audit** (`app/Domain/Audit/Services/AuditLogger.php`): `fromEvent()` / `record(action, payload, type?, id?)`; resolves actor from `admin`/`user` guard or `system`; captures IP + UA.
- **RBAC:** admin-only; **mobile `User` has no roles/permissions**. No `Policy` classes gate mobile auth.
- **Mobile profile:** **no `GET /v1/me`, no profile-update endpoint.** `full_name`/`email` are readable (in auth response via `UserSelfResource`) but **immutable via API today**.

---

## 10. Gaps blocking the requested registration flow

1. **No registration endpoint** (`/v1/auth/register`) and no email-verify endpoint.
2. **OTP engine cannot target email** — `otps.phone` is NOT NULL, no `email`/`channel` column, `OtpService` only takes a phone; no `EmailOtpTransport`.
3. **`email_verify` purpose is dormant** — never issued/consumed.
4. **`RuntimeMailer` is plain-text only** — no HTML body path, no template view.
5. **`email_verified_at` is never set** by any flow.
6. **No anti-enumeration story for email** (unique constraint would otherwise leak existence).
7. **No `full_name` from first/last** — `users.full_name` is a single column; registration collects first + last separately.

> All gaps are closable by **additive extension + reuse**. None require redesigning JWT, refresh, Settings, audit, or the SMS path. See `USER_REGISTRATION_ARCHITECTURE.md`, `DATABASE_PLAN.md`, `IMPLEMENTATION_PLAN.md`.
