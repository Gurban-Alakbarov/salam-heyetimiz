# SECURITY REVIEW

> Pre-Flutter audit. **Read-only.** GSM access-control + payments platform: disjoint RS256 JWT (mobile + admin), OTP auth, admin 2FA, HMAC-signed payment webhooks, encrypted settings.

---

## 0. Verdict

**Security grade: STRONG (9.0/10).** The security fundamentals are well above typical for a pre-v1 backend: proper RS256 JWT validation, refresh rotation with reuse-detection, ownership/IDOR enforcement on every mobile resource, permission-gated admin surface with complex scoping, raw-body constant-time HMAC webhook verification with dedup, encrypted secrets with masked output, and comprehensive rate limiting. Findings are Medium/Low hardening items — **no Critical or High issue found.**

---

## 1. What is solid (verified)

| Control | Status |
|---|---|
| **Mass assignment** | Models use `$guarded=['id']`, but every write goes through a FormRequest + an Action that sets fields explicitly — **no `$request->all()` into create/update** found. Sensitive fields (status, role, owner_user_id, email_verified_at, amount_minor, password) are set only by domain code. ✅ |
| **IDOR / ownership** | `OrderPolicy`/`DevicePolicy`/`OpenCommandPolicy`/`SubscriptionPolicy` gate every mobile `{id}` endpoint; non-members get **404** (no enumeration). ✅ |
| **Admin authorization** | Every admin route is `requirePermission(...)`-gated; `complex_manager` scoped via `assertDeviceInScope` (404 out of scope); re-auth (`admin.tfa`) on recovery-code regen. ✅ |
| **JWT** | RS256 signature + `iss` + `aud` + `exp` + `kind` all validated (`JwtService`); mobile + admin keys disjoint; `kid` header. ✅ |
| **Refresh** | SHA-256 hashed at rest, rotated under `lockForUpdate`+transaction, **reuse of a rotated token revokes the whole device family** + fires `RefreshTokenReuseDetected`. Force-logout cutoff (`security.force_logout_at`) enforced in the guard. ✅ |
| **Webhook (BirPay)** | `X-Signature` = Base64 HMAC-SHA256 verified on the **raw body** with `hash_equals` BEFORE the controller (`verify.birpay` middleware + `CaptureRawBody`); dedup by `payload_hash` + `(bank_order_id,bank_status)`; the webhook is a hint, `getOrderStatus` is authoritative. ✅ |
| **Webhook (Traccar)** | shared-secret token verified with `hash_equals`. ✅ |
| **Secrets** | Settings secrets encrypted at rest (Laravel `Crypt`/AES-256); API returns secrets as `''` + a `secrets_set` map; export keeps ciphertext; payment logs use a 28-field allowlist + a daily scanner job flagging PAN/CVV patterns. No secret/key/token returned in any response. ✅ |
| **Rate limiting** | otp-request (3/phone/10m + 30/IP/h), otp-verify (10/phone/10m), register (5/email/h + 20/IP/h), email-login (5/email/10m + 30/IP/h), otp-verify-email (10/email/10m), otp-resend (3/email/10m), open (12/user + 4/device per min), mobile (120/min), admin (600/min), public (30/min). ✅ |
| **Validation** | All write endpoints behind FormRequests with strict rules (phone regex, email:rfc, uuid, numeric bounds). ✅ |
| **Statelessness / CSRF** | API is stateless JWT (no session-cookie auth) → no CSRF surface; docs `/api/*` gated by HTTP Basic Auth in production. ✅ |
| **File upload** | No file-upload surface in the API. ✅ |

---

## 2. Findings (hardening — Medium/Low)

### SEC-1 — Complex-manager device query loads before scoping (MEDIUM → effectively Low)
`AdminDeviceController::show/update/...` calls `Device::findOrFail($id)` then `assertDeviceInScope()` (404 if out of scope). The outcome is correct (no data leaked), but the row is loaded before the scope check, so a `complex_manager` could in theory infer a device's *existence* via timing.
**Fix direction:** pre-filter the query: `->when($scope, fn($q)=>$q->where('complex_id',$scope))->findOrFail()`. Low real risk; tidy before GA.

### SEC-2 — Inline email/SMS on the request path (MEDIUM — also Performance)
OTP/registration emails (`RegisterUser → issueToEmail → SMTP`) and phone OTP (`SendOtp → SMS HTTP`) are sent **synchronously**. Beyond latency (see `PERFORMANCE_REVIEW`), this is a mild **resource-exhaustion amplification** vector: each register/login request holds a worker through an external I/O. Rate limits mitigate it, but queueing the dispatch removes the amplification entirely.
**Fix direction:** queue `SendEmailOtpJob` / `SendSmsOtpJob`; return the 202 immediately (the contract already supports it).

### SEC-3 — Admin test/ops endpoints not rate-limited (LOW)
`settings/email/send-test`, `send-test-otp`, `sms/send-test`, `payments/test-create` are permission-gated but have no per-endpoint throttle. A compromised admin token could spam test emails / create sandbox payments.
**Fix direction:** add `throttle:admin` (or a tighter custom limiter) to the test routes.

### SEC-4 — No webhook replay-window (LOW, defense-in-depth)
BirPay dedup (payload_hash + unique constraint) already prevents replay of a *seen* body. A timestamp/age check (reject callbacks older than N minutes) would add belt-and-suspenders. Optional.

### SEC-5 — `$guarded=['id']` is allow-all-but-id (LOW, latent)
Today no controller mass-assigns request input, so this is safe. But it's a latent footgun: a future careless `Model::create($request->validated())` could set a sensitive column. **Fix direction:** prefer explicit `$fillable` on the sensitive models (users, admin_users, orders, devices, subscriptions), or keep a lint rule. Document the convention.

### SEC-6 — Settings import across APP_KEY (LOW)
Importing an export onto a server with a different `APP_KEY` decrypts secrets as garbage (caught by `tryDecrypt` fallback, not silently corrupting). Add an APP_KEY/schema check to the import + a migration note. Already gracefully handled.

---

## 3. Pre-launch security checklist (ops, not code)
- [ ] Production `.env`: `QUEUE_CONNECTION=redis`, `CACHE` Redis, `APP_DEBUG=false`, strong `APP_KEY` backed up.
- [ ] `security.force_logout_at` = 0 in prod (not mid-cutoff).
- [ ] BirPay webhook secret + Traccar token set in prod Settings (encrypted).
- [ ] Docs basic-auth password rotated from the default; JWT keypairs present in `storage/keys` / env.
- [ ] Redis is HA (rate-limit + idempotency + cache depend on it).
- [ ] Confirm SEC-1 scoping + SEC-3 throttle (cheap, pre-GA).

---

## 4. Summary

| Severity | Count | Items |
|---|---|---|
| Critical | 0 | — |
| High | 0 | — |
| Medium | 2 | SEC-1 (scope pre-filter), SEC-2 (queue email/SMS) |
| Low | 4 | SEC-3 (throttle test endpoints), SEC-4 (replay window), SEC-5 (`$fillable` convention), SEC-6 (import APP_KEY) |

**No security blocker for Flutter or v1.0.** The mobile auth/registration/bootstrap surface is safe to integrate. Apply SEC-1 + SEC-3 before GA; SEC-2 before scale.
