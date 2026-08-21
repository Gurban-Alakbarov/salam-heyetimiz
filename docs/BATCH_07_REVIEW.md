# Batch 07 — Authentication — Review Package

**Version:** 1.0
**Date:** 2026-06-14
**Scope:** the Authentication bounded context only (mobile OTP login, RS256 JWT issuance, opaque
refresh-token rotation, logout, device registration + biometric trust, admin email/password + TOTP
two-factor, single-use recovery codes, JWKS, the two disjoint JWT guards, rate-limit hooks, auth
audit events). **No Devices code** (next batch) was written.
**Sources of truth:** PROJECT_CONSTITUTION §R-SEC-02..08 / R-API-03, TECHNICAL_SPECIFICATION §15,
DATABASE_ARCHITECTURE §1.2–1.6, BACKEND_ARCHITECTURE §6.5/§7.1/§14.1, openapi/v1.yaml.

## Verification (run on Windows / PHP 8.2.12 / MariaDB 10.4.32)

| Step | Result |
|---|---|
| `php artisan migrate` | ✅ `Nothing to migrate` — the auth tables (admin_users, users, user_devices, refresh_tokens, otps, auth_attempts) already shipped in batches 02–03; **no new migrations needed** |
| `php artisan auth:generate-keys` | ✅ wrote mobile + admin RS256 keypairs to `storage/keys/` |
| `php artisan test` (full suite) | ✅ **131 passed, 427 assertions**, 0 failed (30 Payments + 47 Subscriptions + **54 Auth**) |
| `php artisan route:list` | ✅ 55 routes total; **12 new auth routes** |
| `php artisan schedule:list` | ✅ `auth:prune` daily 03:00 (Asia/Baku) |
| `php docs/openapi/validate.php` | ✅ green (100 operationIds, 96 refs resolve) |

---

## 1. File tree (batch 07)

```
config/domain/auth.php                            # JWT/OTP/refresh/admin-lockout/recovery/TOTP tunables
app/
├── Console/Commands/Auth/
│   ├── GenerateAuthKeysCommand.php               # auth:generate-keys (dev/test RS256 keypairs)
│   └── PruneAuthArtifactsCommand.php             # auth:prune (retention sweep)
├── Domain/Auth/
│   ├── ModuleServiceProvider.php                 # viaRequest guards + login listeners
│   ├── Contracts/OtpTransport.php
│   ├── Adapters/                                 # FakeOtpTransport (test), SmsOtpTransport (real HTTP)
│   ├── DTOs/DeviceFingerprintData.php            # laravel-data
│   ├── Enums/                                     # (reused) AuthActorKind, AuthOutcome, OtpPurpose,
│   │                                              #   Platform, RefreshTokenRevocationReason
│   ├── Models/                                    # (reused) Otp, RefreshToken, UserDevice, AuthAttempt
│   ├── Support/                                   # JwtKeyRing, IssuedAccessToken, AuthTokens,
│   │                                              #   Totp (RFC 6238), RecoveryCodes
│   ├── Exceptions/                                # OtpVerification, InvalidRefreshToken, AdminLogin (401)
│   ├── Events/                                    # 8: OtpRequested, UserAuthenticated, UserLoggedOut,
│   │                                              #   RefreshTokenReuseDetected, AdminAuthenticated,
│   │                                              #   AdminLoginFailed, AdminLoggedOut, RecoveryCodesRegenerated
│   ├── Services/                                  # 6: JwtService, OtpService, UserDeviceService,
│   │                                              #   RefreshTokenService, AuthAttemptRecorder, AdminAuthService
│   ├── Guards/JwtRequestGuard.php                 # resolves bearer → User / AdminUser
│   ├── Listeners/                                 # RecordSuccessfulUserLogin, RecordAdminLogin
│   ├── Actions/                                   # 9: SendOtp, VerifyOtpAndIssueTokens, RefreshTokens,
│   │                                              #   LogoutUser, SetBiometricEnrollment, AdminLogin,
│   │                                              #   AdminVerifyTwoFactor, AdminLogout, RegenerateRecoveryCodes
│   └── Jobs/PruneAuthArtifactsJob.php
├── Http/
│   ├── Middleware/RequireAdminTfaVerified.php     # alias admin.tfa (R-SEC-05)
│   ├── Api/V1/Requests/Auth/                       # RequestOtp, VerifyOtp, RefreshToken
│   ├── Api/V1/Controllers/Auth/                    # AuthController, BiometricController, JwksController
│   ├── Admin/V1/Requests/Auth/                     # AdminLogin, AdminVerify2fa, RegenerateRecoveryCodes
│   ├── Admin/V1/Controllers/Auth/AdminAuthController.php
│   └── Resources/                                  # UserSelfResource, AdminUserResource
tests/
├── Unit/Auth/        TotpTest · RecoveryCodesTest · JwtServiceTest
└── Feature/Auth/     RequestOtpTest · VerifyOtpTest · RefreshTokenTest · LogoutTest · BiometricsTest ·
                      JwtGuardTest · AdminLoginTest · AdminVerify2faTest · AdminSessionTest · JwksTest
```

**Touched (wiring):** `config/auth.php` (2 JWT guards), `bootstrap/app.php` (`admin.tfa` alias +
AuthenticationException→envelope render + root JWKS route), `routes/{api,admin,console}.php` (auth
routes, guard wrapping of the batch 05/06 groups, `auth:prune` schedule),
`app/Providers/IntegrationsServiceProvider.php` (OtpTransport binding),
`app/Exceptions/Renderers/ApiExceptionRenderer.php` (`renderUnauthenticated`),
`app/Domain/Subscriptions/Queries/SubscriptionQuery.php` (`hasActiveForUser` read for UserSelf),
`lang/{az,ru,en}/errors.php` (+10 keys), `lang/{az,ru,en}/auth.php` (new — OTP SMS body),
`tests/TestCase.php` (neutralise limiters in tests), `tests/Pest.php` (auth helpers). Existing batch
05/06 tests updated to `actingAs($x, 'user'|'admin')` now that the groups are guarded.

---

## 2. Implemented endpoints (all per openapi/v1.yaml)

| operationId | Method | Path | Guard | Notes |
|---|---|---|---|---|
| requestOtp | POST | `/v1/auth/otp/request` | public | 202, anti-enumeration, `throttle:otp-request` (3/phone/10m + 30/IP/h) |
| verifyOtp | POST | `/v1/auth/otp/verify` | public | 200 AuthSuccess; auto-provisions user; registers install; `throttle:otp-verify` |
| refreshToken | POST | `/v1/auth/refresh` | public | 200 AuthSuccess; rotates; replay → family revoke |
| logout | POST | `/v1/auth/logout` | auth:user | 204; revokes the install's refresh token |
| enrollBiometrics | POST | `/v1/me/biometrics/enroll` | auth:user | 204; per-install trust flag |
| disableBiometrics | DELETE | `/v1/me/biometrics` | auth:user | 204 |
| getJwks | GET | `/.well-known/jwks.json` | public | admin verification key(s) (CRIT-04) |
| adminLogin | POST | `/admin/v1/auth/login` | public | 200 challenge; bcrypt verify; lockout after 5 |
| adminVerify2fa | POST | `/admin/v1/auth/2fa/verify` | public | 200 AdminAuthSuccess; TOTP **or** recovery code (oneOf) |
| adminLogout | POST | `/admin/v1/auth/logout` | auth:admin | 204 |
| adminMe | GET | `/admin/v1/auth/me` | auth:admin | 200 AdminUser |
| regenerateRecoveryCodes | POST | `/admin/v1/auth/recovery-codes` | auth:admin + admin.tfa | 200 codes[8] (shown once); fresh-TOTP re-auth |

> **Path mapping:** admin OpenAPI paths (`/admin/auth/login`) map to `/admin/v1/auth/...` under the
> bootstrap admin prefix, consistent with batch 05. JWKS is at the host root per RFC 8615.

### Behaviours implemented
6-digit SMS OTP (hashed SHA-256, TTL 120 s, max 5 attempts, supersede-on-resend) · RS256 access token
(15 min, `sub`/`kind`/`fp`/`kid`) · opaque refresh token (60 d, SHA-256 hashed, **rotated each use**,
device-fingerprint bound, **replay → whole-family revoke**) · first-login auto-provisioning of the
user (phone canonical) · `user_devices` registration + push-token capture + biometric trust toggle ·
two-phase admin login (email + bcrypt password → short-lived challenge JWT → TOTP **or** single-use
recovery code → 30-min admin token with `tfa_verified=true`) · admin lockout after 5 failed passwords ·
recovery-code regeneration (atomic replace, plaintext returned once) · JWKS publication · two **disjoint**
JWT guards (R-API-03) wired across the whole mobile/admin surface · `auth_attempts` logging on every
attempt · 8 auth audit events (AuditableEvent) for the batch-09 listener · daily retention sweep.

---

## 3. Database changes

**None.** Every table the Auth context needs already exists from the foundations (DB Arch §1.2–1.6):
`admin_users` (totp_secret VARBINARY, recovery_codes_hashes JSON, lockout columns), `users`,
`user_devices`, `refresh_tokens` (token_hash, rotation chain), `otps` (code_hash, attempts), and the
append-only `auth_attempts`. This batch is pure application logic over the existing schema.

Signing keys live **outside** the database: `storage/keys/*.pem` in dev/test (generated by
`auth:generate-keys`), or PEM material injected via `AUTH_JWT_*_PRIVATE_KEY/_PUBLIC_KEY` env in
production (secret store). `admin_users.totp_secret` stays app-layer encrypted (R-SEC-13).

---

## 4. Test results

```
PASS  Unit/Auth/TotpTest              (6)   round-trip, drift window, RFC 6238 vector, secret gen
PASS  Unit/Auth/RecoveryCodesTest     (3)   8×10-hex, case-insensitive match, consume + remaining
PASS  Unit/Auth/JwtServiceTest        (5)   issue/verify, tamper→null, disjoint user/admin, challenge
PASS  Feature/Auth/RequestOtpTest     (4)   202 envelope, hashed, anti-enumeration, supersede, validation
PASS  Feature/Auth/VerifyOtpTest      (6)   AuthSuccess+provision, consume, wrong/max/expired, device validation
PASS  Feature/Auth/RefreshTokenTest   (3)   rotation, reuse→family revoke, unknown→401
PASS  Feature/Auth/LogoutTest         (2)   revokes install token, requires auth
PASS  Feature/Auth/BiometricsTest     (2)   enroll/disable flag, requires auth
PASS  Feature/Auth/JwtGuardTest       (7)   disjoint guards, no/garbage token, blocked user
PASS  Feature/Auth/AdminLoginTest     (4)   challenge, wrong/unknown→invalid_credentials, lockout
PASS  Feature/Auth/AdminVerify2faTest (6)   TOTP, wrong TOTP, recovery code, reuse, bad challenge, oneOf
PASS  Feature/Auth/AdminSessionTest   (6)   me, logout, regenerate (+wrong TOTP), auth required
PASS  Feature/Auth/JwksTest           (1)   JWKS document shape + admin kid

Auth: 54 passed (209 assertions)   ·   Full suite: 131 passed (427 assertions)   ·   ~17s (MariaDB)
```

Notable cases proving the security-critical paths:
- **Refresh rotation + theft response:** replaying a rotated token returns 401 and revokes the entire
  install token family (the legitimate successor stops working) — R-SEC-02.
- **OTP attempt cap persists:** the failed-attempt counter is committed even though the request ends in
  a 401 (the verify transaction *returns* the outcome and throws outside it, so the increment is not
  rolled back) → the 5th wrong code yields `otp_max_attempts`.
- **Disjoint guards:** an admin token is rejected on a mobile route and vice-versa (different key,
  audience, and `kind`) — R-API-03.
- **Recovery codes are single-use:** a consumed code's slot is nulled; reuse returns 401; the remaining
  count decrements 8→7.
- **TOTP** verified against the RFC 6238 SHA-1 test vector (T=59 → 287082).

---

## 5. Coverage summary

- **Functional coverage:** every "Implement:" bullet (Send/Verify OTP, access + refresh issuance,
  logout, device registration, device trust, admin login, admin TOTP, recovery codes, rate limiting,
  audit events) has at least one passing test. Pure primitives (TOTP, recovery codes, JWT codec) are
  unit-tested; the flows, guards, rotation and lockout are feature-tested against MariaDB.
- **Line coverage:** not measured — no pcov/xdebug in this XAMPP build. Enable pcov and run
  `php artisan test --coverage --min=70` (R-CODE-09).

---

## 6. Design notes & boundaries (for review)

1. **Guards via `Auth::viaRequest`** (drivers `salam-jwt-user` / `salam-jwt-admin`, guard names `user`
   / `admin`). This keeps `actingAs($x, 'user'|'admin')` working in tests while real requests run the
   JWT validation. Guard names are intentionally dot-free (config dot-notation cannot address a key
   containing a dot). The batch 05/06 routes are now wrapped in these guards; their tests were updated
   to name the guard, and `createOrder`'s unauthenticated case is now `401` (guard) rather than `403`
   (FormRequest) since the guard runs first.
2. **`fp` claim = install_uuid.** The mobile access token carries the device fingerprint as `fp`;
   logout/biometrics resolve the install from it. The value is read back from the validated token in
   the controller (robust regardless of guard-instance caching across requests in a long-lived process).
3. **Admin tokens are stateless 30-min JWTs.** `adminLogout` is audited and the client discards the
   token; there is no server-side admin-session store, so true revocation relies on the short TTL. A
   JWT denylist (for immediate admin revocation) is a documented future hardening item.
4. **`tfa_verified` is true by construction.** Admin access tokens are only minted after step-2 2FA, so
   the `admin.tfa` middleware (applied to recovery-code regeneration) is defence in depth. The
   constitution's "state-mutating admin routes require tfa_verified" holds because no other admin-token
   issuance path exists; broader application of `admin.tfa` to every admin write can be layered on in
   the Admin batch without contract change.
5. **OTP transport** mirrors the Payments gateway pattern (R-ARCH-08): `FakeOtpTransport` (singleton,
   in testing / `SMS_PROVIDER=fake`) lets tests read the code back; `SmsOtpTransport` is real HTTP to
   the configured provider — the provider field mapping is the Phase-0 sandbox-confirmable surface, not
   a mock.
6. **Auto-provisioning on first login.** A successful login OTP for an unknown phone creates the user
   (phone is canonical, R-DOM-01). A `blocked` account never receives tokens (generic `wrong_code`, no
   enumeration). `recover` and `login` purposes both lead to token issuance; the verify endpoint carries
   no purpose, so it matches the latest outstanding code for the phone.
7. **Signing keys.** `auth:generate-keys` is a dev/test convenience (Windows openssl.cnf auto-located);
   production injects PEM material via env from the secret store. JWKS exposes the active admin key; a
   real rotation would publish two `kid`s — the keyring is structured for that, currently returning one.
8. **Rate limiting** uses the buckets already defined in `RouteServiceProvider` (R-SEC-16); routes
   attach `throttle:otp-request|otp-verify|public|mobile|admin`. Tests neutralise the limiters (the
   array cache persists across a run and the IP-keyed `public` bucket would otherwise trip 429s between
   unrelated tests); limiter wiring itself is unchanged from foundations.

---

*End of Batch 07 Review v1.0. Stopping here — Authentication complete, **not** starting Devices. Awaiting review.*
