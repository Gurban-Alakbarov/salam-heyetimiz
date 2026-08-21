# IMPLEMENTATION PLAN — REGISTRATION MODULE

> Execution plan to run **after approval**. Nothing here is built yet. Cross-refs: `AUTH_REGISTRATION_AUDIT.md`, `USER_REGISTRATION_ARCHITECTURE.md`, `API_SPEC.md`, `DATABASE_PLAN.md`, `FLUTTER_FLOW.md`.

---

## 1. Reuse analysis (§11 of the brief)

### Reuse unchanged
- `JwtService` (access token issuance, RS256, 15 min) — no change.
- `RefreshTokenService` + `refresh_tokens` (rotation, 60 d) — no change.
- `UserDeviceService` + `user_devices` (install binding) — no change.
- `User` model + `users` table — no DDL; new fill logic only.
- `Settings` (catalog, config bridge, encryption) — reuse `otp.*` + `email.*`.
- `OtpThrottle` (Settings-driven limits) — reuse, key by email.
- `AuditLogger` + `AuditableEvent` pipeline — reuse.
- `SmsOtpTransport` + `integrations.sms.*` + `enable_sms_otp` — **untouched** (SMS infra preserved).
- Existing phone-OTP endpoints (`/v1/auth/otp/request`, `/otp/verify`) — left registered, **not modified**.
- `logout` + `refresh` endpoints — reused verbatim.

### Extend (additive edits to existing files)
- `OtpService` — add `issueToEmail()` / `verifyByEmail()` over shared private internals (no duplication; phone methods untouched).
- `RuntimeMailer` — add optional HTML body (`send($to,$subject,$text,$html=null)`); plain-text path unchanged. Used by **every** email type via `TemplatedMailer`.
- `AuthController` — add `register`, `verifyEmail`, `resendOtp`, `login` methods. `login` is shaped to accept an **optional** `password` (forward-compat, §10 of the architecture); today only the OTP path is wired.
- `routes/api.php` — add 4 public routes with throttles.
- `RouteServiceProvider` — add `register` / `login` / `otp-verify-email` / `otp-resend` limiters.
- `SettingsCatalog` — add `email.html_enabled`, `otp.unverified_prune_hours`, and **per-`EmailType` subject/copy** keys (OTP wired; Welcome/Password*/Email*/Security reserved).
- `routes/console.php` — schedule `auth:prune-unverified`.

### New (additive files)
- Actions: `RegisterUser`, `VerifyEmailAndIssueTokens`, `RequestEmailLogin`, `ResendEmailOtp`.
- FormRequests: `RegisterRequest`, `VerifyEmailRequest`, `EmailLoginRequest`, `ResendOtpRequest`.
- **Generic email layer:** `EmailType` enum, `TemplatedMailer` service, base layout `resources/views/emails/layouts/base.blade.php`, `emails/otp.blade.php` (first consumer; `welcome`/`password-set`/`password-changed`/`email-changed`/`security-alert` templates reserved for later).
- Transport: `EmailOtpTransport` (implements `OtpTransport`, calls `TemplatedMailer`).
- Events: `UserRegistered`, `EmailVerified` (auditable).
- Migration: `…add_email_channel_to_otps.php` (otps `email`/`channel` + **reserved nullable `users.password`**).
- Command: `PruneUnverifiedUsers` (`auth:prune-unverified`).
- Tests (see §6).

### Delete — **nothing**
No file is removed. The dormant `OtpPurpose::EmailVerify`/`Recover`, `password_reset_tokens` table, and `tpl_password_reset` setting are **kept** (EmailVerify becomes active; the rest stay reserved for the future Security/password feature).

### Do NOT change
JWT claims/keys, refresh rotation, admin auth, RBAC, Settings storage, audit schema, SMS transport, existing phone-OTP behaviour, and the existing **columns + semantics** of `users`/`refresh_tokens`/`user_devices`/`devices`/`subscriptions`/`complexes`. The only `users` change is the **additive, reserved, nullable `password`** column (unused now — forward-compat for the Security feature).

---

## 2. Build phases

| Phase | Scope | Output | Gate |
|---|---|---|---|
| **P1 — Email channel + generic mail layer** | `otps` migration (+ reserved `users.password`); `OtpService::issueToEmail/verifyByEmail`; generic email layer (`EmailType`, `TemplatedMailer`, base layout + `otp.blade.php`); `EmailOtpTransport`; `RuntimeMailer` HTML | email OTP sent + verified via the generic layer | unit + feature tests green; phone OTP regression green |
| **P2 — Register + Verify** | `RegisterRequest`/`RegisterUser`; `VerifyEmailRequest`/`VerifyEmailAndIssueTokens`; routes; events | `/register` + `/verify-email` working (auto-login) | tests + domain-invariant tests green |
| **P3 — Resend + Email login** | `ResendOtp*`, `EmailLogin*`; routes; limiters | `/resend-otp` + `/login` working | tests green |
| **P4 — Hardening** | rate limiters; anti-enumeration responses; `auth:prune-unverified`; audit events | security tests green | full suite green |
| **P5 — Docs + tooling** | add endpoints to `docs/openapi/v1.extra.yaml`; rebuild spec; regenerate Postman/Bruno | live Swagger shows new endpoints | `/api/openapi.json` updated |
| **P6 — Rollout** | deploy backend; run migration; post-deploy smoke verify | prod live | smoke tests green |

Each phase is independently testable; P1 can merge before P2 etc.

---

## 3. Rate limiting (§6)

Defined in `RouteServiceProvider` next to the existing limiters; **email/IP keyed** (not phone):

| Limiter | Route | Limit |
|---|---|---|
| `register` | `/auth/register` | 5 / email / hour **AND** 20 / IP / hour |
| `login` | `/auth/login` | 5 / email / 10 min **AND** 30 / IP / hour |
| `otp-verify-email` | `/auth/verify-email` | 10 / email / 10 min |
| `otp-resend` | `/auth/resend-otp` | 3 / email / 10 min (+ server enforces `otp_resend_seconds`) |

On top: `OtpThrottle` (Settings `otp_hourly_limit`/`otp_daily_limit`/`otp_temp_block_minutes`, keyed by email) and `brute_force_protection`. All limiters return `429` + `Retry-After`.

---

## 4. Security risks & mitigations (§10)

| Risk | Mitigation |
|---|---|
| **OTP reuse / replay** | single-use `consumed_at` under `lockForUpdate`; superseding prior codes on new issue (existing engine) |
| **Brute force** | `otp_max_attempts` (5) → `otp_max_attempts` 401; `otp-verify-email` HTTP limiter; OtpThrottle temp-block |
| **Account enumeration** | `/register`, `/login`, `/resend-otp` always return the **same 202** regardless of account existence; existing-account case sends a "use Login" email, never an API signal; uniform timing |
| **Email flooding / bombing** | `register` + `otp-resend` limiters (email + IP); `otp_resend_seconds` min-gap; OtpThrottle hourly/daily; per-IP cap |
| **Race conditions** | DB transaction + row lock on verify (existing); unique constraints on `email`/`phone` serialise concurrent registers |
| **Token theft after issue** | refresh rotation + reuse-detection (existing): replay revokes the whole device family |
| **OTP interception** | short TTL (Settings, default 120s); code never logged; HTML email carries a "never share" warning |
| **Unverified-row pollution** | `auth:prune-unverified` scheduled cleanup; verified-only uniqueness checks at register |
| **PII in logs/audit** | audit stores masked email + purpose only; OTP code never persisted in plaintext or logs (SHA-256 at rest) |
| **Open SMTP relay abuse** | mail goes only to the registrant's own submitted address; rate-limited; SMTP creds from encrypted Settings |

---

## 5. Domain invariants enforced (§12)

Registration/verify create **only** a `User`. Tests assert that after `/register` + `/verify-email` there are **0** new rows in `devices`, `device_users`, `subscriptions`, and no `complex_id` / `owner_user_id` writes. Linking to complex/device/residence remains a later admin action.

---

## 6. Test plan (§15)

### Backend / unit
- `OtpService::issueToEmail/verifyByEmail` — generate/hash/store/expire/consume/attempts; phone path unchanged (regression).
- `EmailOtpTransport` — renders Blade → HTML, calls `RuntimeMailer` with html+text.
- `RuntimeMailer` — html path doesn't break the existing text path.
- `RegisterUser` / `VerifyEmailAndIssueTokens` — full_name composition, `email_verified_at` set, token issuance, events dispatched.

### API / feature (Pest, against MariaDB `salam_testing`)
- `register` → 202; creates unverified user; no tokens.
- `verify-email` happy path → 200 + tokens + `email_verified_at` set + `UserRegistered`/`UserAuthenticated` audited.
- `verify-email` wrong/expired/max → 401 codes.
- `resend-otp` respects `otp_resend_seconds` + limiter.
- `login` (verified) → 202 → `verify-email` → 200 tokens.
- `login`/`register` for existing/non-existing → identical 202 (enumeration test).
- Validation: bad email/phone/name → 422 `error.fields`.
- **Domain invariant**: post-flow, 0 devices/roster/subscriptions created.
- **Regression**: existing phone `otp/request`+`otp/verify` still pass unchanged.

### OTP tests
- TTL/length/resend/max read from Settings (override Settings → behaviour changes; no hardcode).
- Single-use; superseding; attempt counter; channel routing (email vs sms).

### Email tests
- `Mail`/transport faked: subject + HTML body contain the code + TTL minutes; From/Reply-To from Settings; plain-text fallback present.

### Security tests
- Rate limiters trip at the configured thresholds (429 + Retry-After).
- OtpThrottle block path.
- No OTP code in logs/audit payloads; email masked.

### E2E (pre-prod, prod-like)
- Production SMTP (Brevo, live Settings) → real inbox: register → receive HTML OTP → verify → tokens → call an authed endpoint → logout. (SMTP already verified delivering to Gmail; use a throwaway address.)

### Flutter
- Widget/state tests for: Splash routing (refresh ok/expired), Register form validation, Verify countdown + resend-disable, Expired/Too-Many-Attempts states, secure-storage token persistence, 401→refresh→retry interceptor.

**Target:** full backend suite stays green (current 321) + the new module's ~25–35 cases.

---

## 7. Production rollout (§14)

1. **Pre-deploy:** review migration on a copy of prod data; confirm `otps` row count (online ALTER if large). **SMTP is production-ready** — Brevo with domain verification + SPF/DKIM active, runtime SMTP Settings live, Test Email + Test OTP already deliver to real inboxes (Gmail confirmed); **no SMTP blocker remains.**
2. **Deploy order:** code first (new endpoints behind the new routes; harmless if unused) → run migration (`php artisan migrate --force`, additive) → `composer dump-autoload -o` → `route:cache` + `config:cache` → reload `php8.4-fpm` → restart `salam-horizon` (queue/listeners).
3. **Seed Settings:** `db:seed` for the 2 new catalog keys (idempotent).
4. **Rebuild API docs:** `node docs/openapi/build-openapi.mjs` + `php artisan docs:generate`, redeploy `openapi.json` + collections.
5. **Smoke test (prod, throwaway email):** register → verify → tokens → logout. Confirm audit entries + no device/subscription side-effects.
6. **Feature gate (optional):** keep `email.enable_email_otp` / a route toggle so the flow can be dark-launched and enabled per release.

## 8. Rollback (§14)

- **Code rollback:** redeploy the previous build. The added `otps.email`/`channel` columns are nullable + defaulted → the old code ignores them harmlessly (no DB rollback needed for a code-only revert).
- **Full rollback:** revert code, then `migrate:rollback` the single migration (drops `email`/`channel`/index; restores `phone NOT NULL` — safe while no email OTP rows exist; if email rows exist, delete them first or leave the columns in place). No data loss to `users`/`refresh_tokens`.
- **Kill switch:** disable the new routes (feature flag / comment the route group) without touching the DB — instantly stops registration while leaving login/refresh intact.

## 9. Risks & watch-items

| Risk | Severity | Handling |
|---|---|---|
| Email deliverability (spam folder / bounces) | Low | SMTP already production-ready (Brevo, verified domain, SPF/DKIM active, Gmail delivery confirmed); ongoing: monitor bounce/complaint rates |
| `otps.phone` nullable ALTER on large table | Med | online DDL / `pt-osc` if needed; test on prod-size copy |
| Existing phone-only users colliding on phone/email | Med | "complete profile" path for unverified existing rows; clear 422 otherwise |
| Enumeration via timing | Med | uniform 202 + constant-time-ish handling; do the same work (or dummy) on non-existent accounts |
| Unverified-row buildup | Low | `auth:prune-unverified` scheduled |
| Two verify surfaces confusion | Low | single `/verify-email` for both registration + login (documented) |

---

## 10. Definition of done

All 6 phases merged; full backend suite green incl. new cases; domain-invariant + enumeration + rate-limit tests green; HTML OTP email delivered E2E to a real inbox (production SMTP — Brevo); new endpoints live in `/api/docs` (Swagger/ReDoc/Postman/Bruno); rollout + rollback rehearsed; **no existing endpoint or behaviour changed**; SMS path untouched. Then implementation is complete and ready for production enable.
