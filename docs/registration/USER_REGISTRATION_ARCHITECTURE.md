# USER REGISTRATION — ARCHITECTURE

> Planning document. No code is written here. This is the design that `IMPLEMENTATION_PLAN.md` executes after approval.
> **Principle:** extend, don't redesign. Reuse `User`, `JwtService`, `RefreshTokenService`, `OtpService`, `RuntimeMailer`, `Settings`, `AuditLogger`. Add only an **email channel** to the OTP engine and the **registration/verify/login** entry points. SMS path untouched.

---

## 1. Model (confirmed by product owner)

- Passwordless, **Email-OTP** for both **registration** and **primary login**.
- Registration collects: **First Name, Last Name, Mobile Number, Email**. **No password.**
- A password may be set **later**, voluntarily, in a personal-cabinet "Security" section. This module stays passwordless, **but is architected so that Email + Password login can be added later as pure additive code — no auth rewrite** (§9 Forward-compatibility). A nullable `users.password` column is reserved now.
- **SMS login removed from the product surface; SMS infrastructure code untouched** (`SmsOtpTransport`, `integrations.sms.*`, `enable_sms_otp` remain in place, just not used by the new flows).

---

## 2. Account lifecycle (states)

```
(none) ──register──▶ REGISTERED_UNVERIFIED ──verify-email──▶ ACTIVE_VERIFIED ──▶ (tokens issued = logged in)
                         │  email_verified_at = NULL              │ email_verified_at = now()
                         │  status = active                       │ status = active
                         │  NO tokens issued                      │ JWT + refresh issued
                         └── stale (TTL) ──prune job──▶ deleted (optional cleanup)
```

- `users.status` enum is **not changed** (no new `pending` value). The verification gate is **`email_verified_at`** (NULL = unverified). This avoids a risky enum migration and reuses the existing `Active` semantics.
- A user is **only "logged in" (tokens issued) after email verification.** Register alone returns no tokens.

> Rationale for creating the row at `/register` (vs. deferring to verify): maximal reuse of `User` + the unique constraints, and it lets `email_verified_at` act as the single source of truth. Stale unverified rows are pruned by a scheduled job (see §8). The rejected alternative (a `pending_registrations` table / Redis blob) is documented in `DATABASE_PLAN.md §5`.

---

## 3. Flows

### 3.1 Registration
```
Client                         API                                 Engine
  │  POST /v1/auth/register      │                                   │
  │  {first_name,last_name,      │  validate (unique email+phone)    │
  │   phone,email}               │  upsert User(full_name=          │
  │ ───────────────────────────▶│   "First Last", phone, email,     │
  │                              │   email_verified_at=NULL,         │
  │                              │   status=active)                  │
  │                              │  OtpService.issueToEmail(         │
  │                              │    email, EmailVerify) ──────────▶│ generate+hash+store(channel=email)
  │                              │                                   │ EmailOtpTransport → RuntimeMailer(HTML)
  │  202 {expires_in_seconds,    │◀──────────────────────────────────│
  │       resend_available_in}   │  (generic; no account-existence leak)
  │◀─────────────────────────────│
```

### 3.2 Verify email → auto-login
```
  │  POST /v1/auth/verify-email   │
  │  {email,code,device{...}}     │  OtpService.verifyByEmail(email, code, EmailVerify)
  │ ────────────────────────────▶ │  → set users.email_verified_at = now()
  │                               │  → UserDeviceService.registerOrUpdate(device)
  │                               │  → RefreshTokenService.issue()
  │                               │  → JwtService.issueUserAccessToken()
  │  200 {access_token,           │  → dispatch UserRegistered + UserAuthenticated
  │       refresh_token,user{...}}│
  │◀──────────────────────────────│   (identical envelope to existing verifyOtp)
```

### 3.3 Resend OTP
```
  │  POST /v1/auth/resend-otp {email}  │  respects otp.otp_resend_seconds (Settings) + rate limits
  │ ──────────────────────────────────▶│  OtpService.issueToEmail(email, <pending purpose>)
  │  202 {expires_in_seconds,resend...}│
```

### 3.4 Login (returning, verified user) — Email OTP
```
  │  POST /v1/auth/login {email}        │  if user exists & email_verified_at != NULL:
  │ ───────────────────────────────────▶│    OtpService.issueToEmail(email, Login)
  │  202 {expires_in_seconds,resend...} │  (generic response either way → no enumeration)
  │  POST /v1/auth/verify-email          │  same verify endpoint; on success issues tokens.
  │  {email,code,device{...}}            │  (does not re-set email_verified_at if already set)
```
> `verify-email` is the single "verify an email OTP → issue tokens" endpoint, used by both registration (purpose `email_verify`) and login (purpose `login`). It sets `email_verified_at` idempotently. This keeps one verification surface (reuse) rather than two near-identical endpoints. (API names finalised in `API_SPEC.md`.)

### 3.5 Logout / Refresh
Unchanged — reuse `POST /v1/auth/logout` and `POST /v1/auth/refresh` exactly as they are.

---

## 4. Component map (reuse vs. add)

| Concern | Component | Action |
|---|---|---|
| Identity | `User` model + `users` table | **Reuse**; new column-fill logic only (full_name from first+last) |
| Access token | `JwtService::issueUserAccessToken` | **Reuse unchanged** |
| Refresh | `RefreshTokenService` + `refresh_tokens` | **Reuse unchanged** |
| Device binding | `UserDeviceService` + `user_devices` | **Reuse unchanged** |
| OTP core | `OtpService` | **Extend** with `issueToEmail()` / `verifyByEmail()` (reuse internals) |
| OTP store | `otps` table | **Extend** (+`email` nullable, +`channel` enum, `phone` → nullable) |
| OTP delivery | `OtpTransport` contract | **Add** `EmailOtpTransport` impl (SMS impl untouched) |
| Email transport | `RuntimeMailer` (**production-ready**: Brevo, domain verified, SPF/DKIM) | **Extend** with an optional HTML body (additive param/overload) |
| Email templates | (none today) | **Add** a **generic templated-email system** — base layout + per-type templates; OTP is its first consumer (§7) |
| Email composition | (none today) | **Add** `EmailType` enum + `TemplatedMailer` service: `{type,data}` → subject + HTML + text → `RuntimeMailer` |
| OTP params | `Settings` (`otp.*`, `email.*`) | **Reuse** — TTL/resend/length/templates read from Settings (no hardcode) |
| Throttle | `OtpThrottle` + HTTP limiters | **Reuse** + **add** email-keyed limiters (§6 of API_SPEC) |
| Audit | `AuditLogger` + events | **Reuse** + add `UserRegistered`, `EmailVerified` auditable events |
| Entry points | `AuthController` (+ Requests/Actions) | **Add** `register`, `verifyEmail`, `resendOtp`, `login` methods + FormRequests + Actions |

New classes (created at implementation time, not now): `RegisterUser`, `VerifyEmailAndIssueTokens`, `RequestEmailLogin`, `ResendEmailOtp` (Actions); `RegisterRequest`, `VerifyEmailRequest`, `ResendOtpRequest`, `EmailLoginRequest` (FormRequests); `EmailOtpTransport`; `TemplatedMailer` + `EmailType` (generic email layer, §7); `UserRegistered`, `EmailVerified` (Events). All additive — **no existing file is replaced**, only `OtpService`, `RuntimeMailer`, `AuthController`, `routes/api.php`, `RouteServiceProvider`, `SettingsCatalog` (email template/subject keys per type) are **extended**. The migration also reserves a nullable `users.password` column for the future Security feature (§9).

---

## 5. OTP engine extension (the only non-trivial change)

`OtpService` gains two methods that **reuse the existing generate/hash/store/verify internals**, keyed on email + `channel='email'`:

- `issueToEmail(string $email, OtpPurpose $purpose, string $ip, string $locale): array`
- `verifyByEmail(string $email, string $code, ?OtpPurpose $purpose): void`

The existing `issue($phone,…)` / `verify($phone,…)` stay byte-for-byte. Internally both paths converge on a private `generateAndStore(destination, channel, purpose, …)` and `consume(destination, channel, …)` so logic is **not duplicated**. Delivery is chosen by `channel`: `email → EmailOtpTransport`, `sms → SmsOtpTransport`.

`OtpPurpose` reuses the **already-defined `EmailVerify`** case (currently dormant) for registration, and `Login` for email login. No new enum values strictly required (optionally add `EmailVerify` to allowed request purposes).

---

## 6. Domain rules (hard invariants — §12 of the brief)

At registration / verification the module creates **only a `User` row**. It MUST NOT:
- create a `Device` or touch `devices`;
- create a `device_users` (roster / resident) row;
- create a `Subscription`;
- assign a `Complex` (no `users.complex_id` is even written);
- create a "device owner" (`devices.owner_user_id` untouched).

Linking a user to a complex / device / residence happens **later**, exclusively via the existing admin (Super Admin / Device Owner) flows. Registration is a pure account-creation boundary. These invariants are asserted by tests (see `IMPLEMENTATION_PLAN.md §Test Plan`).

---

## 7. Generic email template system (OTP is the first consumer)

The email layer is built **generic from day one** — not OTP-specific — so every future transactional email reuses the same infrastructure with no rework.

**Building blocks (added at implementation):**
- **Base layout** `resources/views/emails/layouts/base.blade.php` — shared shell (logo/header, content slot, security-note slot, footer). All emails `@extends` it.
- **Per-type templates** `resources/views/emails/{type}.blade.php` — e.g. `otp`, `welcome`, `password-set`, `password-changed`, `email-changed`, `security-alert`.
- **`EmailType` enum** + **`TemplatedMailer` service** — `TemplatedMailer::send(EmailType $type, string $to, array $data, string $locale)` resolves the subject (Settings, per type), renders the Blade view → HTML, builds a plain-text fallback, and calls `RuntimeMailer`. `EmailOtpTransport` is simply the OTP caller of `TemplatedMailer`.
- **Settings** — per-type subject + short-copy keys (extending the existing `otp.tpl_*` pattern), so all email copy stays admin-editable (no hardcode); TTL/branding also from Settings.

**Planned `EmailType` set** (this module wires only `RegistrationOtp` + `LoginOtp`; the rest are reserved — zero code change later, just a template + enum case):
`RegistrationOtp`, `LoginOtp`, `Welcome`, `PasswordSet`, `PasswordChanged`, `EmailChanged`, `SecurityAlert`, … (extensible).

**SMTP is production-ready** — **Brevo** with domain verification complete, **SPF + DKIM active**, runtime SMTP Settings live; **Test Email + Test OTP already deliver to real inboxes (Gmail confirmed)**. No SMTP work remains — only the templates + composition layer are new.

**OTP template** (`emails/otp.blade.php`, first consumer of the base layout) — variables `$code`, `$ttlMinutes` (= `otp.otp_ttl_seconds`/60), `$intro` (`otp.tpl_registration`/`tpl_login`), `$brand`, `$supportEmail`; **TTL from Settings, never hardcoded:**

```html
<!doctype html>
<html lang="az"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f4f5f7;font-family:Segoe UI,Arial,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="480" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);">
        <!-- Logo / header -->
        <tr><td style="background:#6d28d9;padding:20px 28px;">
          <span style="color:#fff;font-size:18px;font-weight:700;">Salam Həyətimiz</span>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:28px;">
          <p style="margin:0 0 8px;font-size:16px;font-weight:600;">Təsdiq kodunuz</p>
          <p style="margin:0 0 20px;font-size:14px;color:#6b7280;">{{ $intro }}</p>
          <div style="text-align:center;margin:8px 0 20px;">
            <span style="display:inline-block;font-size:34px;letter-spacing:10px;font-weight:700;
                         color:#111827;background:#f3f4f6;border-radius:10px;padding:14px 22px;">{{ $code }}</span>
          </div>
          <p style="margin:0 0 18px;font-size:13px;color:#6b7280;text-align:center;">
            Kod <strong>{{ $ttlMinutes }} dəqiqə</strong> ərzində etibarlıdır.</p>
          <!-- Security warning -->
          <table role="presentation" width="100%" style="background:#fef2f2;border-radius:8px;">
            <tr><td style="padding:12px 14px;font-size:12px;color:#991b1b;">
              ⚠️ Bu kodu heç kimlə paylaşmayın. Salam Həyətimiz əməkdaşları sizdən kodu soruşmaz.
              Bu sorğunu siz etməmisinizsə, məktubu nəzərə almayın.</td></tr>
          </table>
        </td></tr>
        <!-- Footer -->
        <tr><td style="padding:16px 28px;background:#fafafa;border-top:1px solid #eee;
                       font-size:11px;color:#9ca3af;">
          © {{ date('Y') }} Salam Həyətimiz · Dəstək: {{ $supportEmail }}<br>
          Bu avtomatik mesajdır, cavab verməyin.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
```

`RuntimeMailer` is extended **once** to accept an optional HTML body (`send($to,$subject,$text,$html=null)` → `->text($text)->html($html)`); the existing plain-text test path is unaffected. Every email type flows through `TemplatedMailer` → `RuntimeMailer`, so adding the Welcome / Password / Security emails later is **a template + an enum case only — no transport or sending changes**.

---

## 8. Cleanup & scheduling

- A scheduled command (e.g. `auth:prune-unverified`) deletes `users` rows where `email_verified_at IS NULL AND created_at < now - {grace}` (grace from a new `otp`/`auth` setting, default e.g. 72h). Reuses the existing console-scheduler in `routes/console.php`. Optional but recommended to avoid unverified-row accumulation.
- Existing `otps` pruning (by `expires_at`) already applies to email rows.

---

## 9. What is explicitly NOT redesigned

JWT issuance/claims, refresh rotation, device binding, the SMS transport, admin auth, Settings storage/encryption, audit pipeline, RBAC. The pre-existing **phone-OTP endpoints remain registered and untouched** (additive coexistence) so nothing currently calling them breaks; the product simply steers new clients to the email endpoints. Deprecation (if ever desired) is a later, separate decision.

---

## 10. Forward-compatibility: password login & the Security cabinet

Today login is **Email OTP**. The user will later be able to set a password from a personal-cabinet **Security** section, after which **Email + Password** becomes an alternative login. The current architecture is built so that this is **purely additive — the auth system is not rewritten**. Three seams make that true:

**1. Credential-agnostic token issuance.** Tokens are minted by `JwtService` + `RefreshTokenService`, which **do not care how the user authenticated**. Both OTP-verify and a future password-verify converge on the same `issueTokens(User, device)` step. Adding a password path adds a new *verifier*, not a new token pipeline.

**2. A login endpoint shaped for both factors.** `POST /v1/auth/login` is specified to **optionally** accept a `password` field:
- `password` present **and** the user has a password set → verify the password directly and issue tokens (Email + Password login).
- otherwise → send an email login OTP (today's only path).

So the password path slots into the **existing** endpoint with no breaking change to clients that send only `email`.

**3. Reserved schema + email types.** A **nullable `users.password`** column is added now (reserved, unused by this module — see `DATABASE_PLAN.md`). The generic email system (§7) already reserves `PasswordSet`, `PasswordChanged`, `EmailChanged`, `SecurityAlert` types, so the Security cabinet's notifications need only a template, not new infrastructure.

**Future endpoints (planned, not built now):** `POST /v1/me/password` (set — requires a verified session; sends `PasswordSet`), `PATCH /v1/me/password` (change — re-auth; sends `PasswordChanged`), `PATCH /v1/me/email` (change + re-verify via OTP; sends `EmailChanged`). All reuse `JwtService`, `RefreshTokenService`, `OtpService`, and `TemplatedMailer`.

**Net effect:** when the Security feature lands, the work is `users.password` fill logic + a password verifier + the `/me/*` endpoints + templates — **zero changes to OTP, JWT, refresh, or the login contract**. No auth rewrite.
