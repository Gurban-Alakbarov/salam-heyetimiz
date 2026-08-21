# REGISTRATION API — SPEC

> Planning contract. Prefix **`/v1`** (mobile surface). All bodies JSON; all times UTC ISO-8601; phone `+994XXXXXXXXX`. Error envelope is the existing `{ "error": { code, message_key, message, details, request_id } }`; validation adds `error.fields`.
> **Reuse:** `logout` + `refresh` are the existing endpoints, unchanged. Everything else is **new + additive**. The existing phone-OTP endpoints (`/v1/auth/otp/request`, `/v1/auth/otp/verify`) stay registered and are not modified.

---

## 0. Endpoint map

| # | Method | Path | Auth | New? | Purpose |
|---|---|---|---|---|---|
| 1 | POST | `/v1/auth/register` | Guest | **new** | Create unverified account + send email OTP |
| 2 | POST | `/v1/auth/verify-email` | Guest | **new** | Verify email OTP → set `email_verified_at` → issue tokens (auto-login) |
| 3 | POST | `/v1/auth/resend-otp` | Guest | **new** | Re-send the email OTP |
| 4 | POST | `/v1/auth/login` | Guest | **new** | Request an email login OTP (returning verified user) |
| 5 | POST | `/v1/auth/logout` | `auth:user` | reuse | Revoke this install's refresh tokens |
| 6 | POST | `/v1/auth/refresh` | Guest (token in body) | reuse | Rotate access + refresh |

> Endpoints 2 handles **both** registration-verify and login-verify (purpose inferred from the latest pending OTP for that email). No separate `verify-login`.

---

## 1. POST /v1/auth/register

Create (or complete) an unverified account and dispatch a 6-digit OTP to the email.

**Auth:** none (guest). **Rate limit:** `throttle:register` → `5 / email / hour` **AND** `20 / IP / hour`.
**Headers:** `Content-Type: application/json`, optional `Accept-Language: az|ru|en`.

**Request**
```json
{ "first_name": "Aysel", "last_name": "Məmmədova",
  "phone": "+994501234567", "email": "aysel@example.com" }
```

**Validation (`RegisterRequest`)**
| Field | Rules |
|---|---|
| `first_name` | required, string, 1–60, letters/space/`-`/`'` |
| `last_name` | required, string, 1–60, same charset |
| `phone` | required, `regex:/^\+994\d{9}$/`, unique among **verified** users (see §Domain) |
| `email` | required, email (RFC + DNS-lenient), max 160, unique among **verified** users |

**Success — 202 Accepted** (generic; identical whether or not the email already exists, to prevent enumeration)
```json
{ "expires_in_seconds": 120, "resend_available_in_seconds": 30 }
```

**Errors**
| Status | code | When |
|---|---|---|
| 422 | `validation_failed` | bad/missing fields (`error.fields`) |
| 429 | `rate_limited` | register limiter exhausted (`Retry-After`) |
| 429 | `otp_rate_limited` / `otp_temporarily_blocked` | `OtpThrottle` (Settings) tripped |

> If `email`/`phone` belongs to an **already-verified** account, the API still returns **202** but sends a "you already have an account — use Login" email instead of an OTP (no API-level existence signal). If it belongs to an **unverified** account, the row is updated (name/phone) and a fresh OTP is sent.

---

## 2. POST /v1/auth/verify-email

Verify the email OTP, mark the email verified, register the install, and issue tokens (auto-login). Used by registration **and** login verification.

**Auth:** none. **Rate limit:** `throttle:otp-verify-email` → `10 / email / 10 min`.

**Request**
```json
{ "email": "aysel@example.com", "code": "482931",
  "device": { "install_uuid": "1f3b…uuid", "platform": "ios",
              "os_version": "17.4", "app_version": "1.0.0",
              "device_model": "iPhone15,2", "push_token": "fcm…" } }
```

**Validation (`VerifyEmailRequest`)** — `email` required/email; `code` `regex:/^\d{6}$/`; `device.install_uuid` uuid; `device.platform` `in:ios,android`; optional `os_version≤40`, `app_version≤20`, `device_model≤80`, `push_token≤255`.

**Success — 200 OK** (same envelope as the existing `verifyOtp`)
```json
{ "access_token": "<RS256 JWT>", "refresh_token": "<opaque>",
  "token_type": "Bearer", "expires_in": 900, "refresh_expires_in": 5184000,
  "user": { "id": 42, "phone": "+994501234567", "full_name": "Aysel Məmmədova",
            "email": "aysel@example.com", "email_verified_at": "2026-06-29T10:05:00Z",
            "preferred_language": "az", "status": "active",
            "created_at": "2026-06-29T10:00:00Z", "last_login_at": "2026-06-29T10:05:00Z",
            "has_active_subscription": false } }
```

**Errors**
| Status | code | When |
|---|---|---|
| 401 | `wrong_code` | code mismatch (attempt counted) |
| 401 | `otp_expired` | past `expires_at` |
| 401 | `otp_max_attempts` | > `otp.otp_max_attempts` |
| 422 | `validation_failed` | bad fields |
| 429 | `rate_limited` | verify limiter exhausted |

> On success for a registration OTP → sets `email_verified_at`. For a login OTP on an already-verified user → leaves `email_verified_at` unchanged. Both issue tokens.

---

## 3. POST /v1/auth/resend-otp

**Auth:** none. **Rate limit:** `throttle:otp-resend` → `3 / email / 10 min` **AND** server enforces `otp.otp_resend_seconds` (min gap).

**Request** `{ "email": "aysel@example.com" }`
**Success — 202** `{ "expires_in_seconds": 120, "resend_available_in_seconds": 30 }`
**Errors:** 422 validation; 429 `rate_limited` / `otp_resend_too_soon` (with `Retry-After`); generic 202 if no pending registration (no enumeration).

---

## 4. POST /v1/auth/login

Request an email login OTP for a **returning, verified** user. Verification is completed via **endpoint 2**.

**Auth:** none. **Rate limit:** `throttle:login` → `5 / email / 10 min` **AND** `30 / IP / hour`.

**Request** `{ "email": "aysel@example.com" }`
**Success — 202** `{ "expires_in_seconds": 120, "resend_available_in_seconds": 30 }` (generic — sent only if a verified account exists; response identical otherwise).
**Errors:** 422 validation; 429 `rate_limited` / `otp_rate_limited`.

> **Forward-compatible (not wired now):** this endpoint is specified to also accept an optional **`password`**. When the future Security feature lets a user set a password, `POST /auth/login {email,password}` → if the user has a password set, verify it and issue tokens **directly** (Email + Password login, 200 with the same token envelope as §2); otherwise fall back to sending the email OTP. Clients sending only `email` are unaffected — **no breaking change, no auth rewrite**. See `USER_REGISTRATION_ARCHITECTURE.md §10`.

### Future endpoints (planned, NOT in this module)
`POST /v1/me/password` (set password → `PasswordSet` email), `PATCH /v1/me/password` (change → `PasswordChanged` email), `PATCH /v1/me/email` (change + re-verify via OTP → `EmailChanged` email). All reuse `JwtService` / `RefreshTokenService` / `OtpService` / the generic `TemplatedMailer`. Listed here only to confirm the contract is designed to absorb them additively.

---

## 5. POST /v1/auth/logout  *(reuse — unchanged)*

**Auth:** `auth:user`. Revokes active refresh tokens for the calling install (`fp` claim). **204 No Content.** 401 if token missing/invalid.

## 6. POST /v1/auth/refresh  *(reuse — unchanged)*

**Auth:** none (refresh token in body). `{ "refresh_token": "<opaque>" }` (`min:30,max:200`). **200** → same token envelope as §2. **401** `invalid_refresh_token` (incl. replay → whole device family revoked).

---

## 7. Error model (canonical)

```json
{ "error": { "code": "wrong_code", "message_key": "errors.wrong_code",
             "message": "Kod yanlışdır.", "details": null,
             "request_id": "01KW…" } }
```
Validation adds `error.fields`:
```json
{ "error": { "code": "validation_failed", "message_key": "errors.validation",
             "message": "…", "fields": { "email": ["Bu email artıq istifadədədir."] },
             "request_id": "01KW…" } }
```

**Error code catalog (this module)**
| code | HTTP | Meaning |
|---|---|---|
| `validation_failed` | 422 | field errors in `error.fields` |
| `wrong_code` | 401 | OTP mismatch |
| `otp_expired` | 401 | OTP past TTL |
| `otp_max_attempts` | 401 | OTP attempts exceeded |
| `otp_resend_too_soon` | 429 | resend before `otp_resend_seconds` |
| `otp_rate_limited` | 429 | OtpThrottle hourly/daily |
| `otp_temporarily_blocked` | 429 | OtpThrottle temp block |
| `rate_limited` | 429 | HTTP limiter (register/login/verify/resend) |
| `invalid_refresh_token` | 401 | refresh reuse/expiry |

---

## 8. Cross-cutting

- **Pagination / Filters / Sorting:** **N/A** — none of these endpoints list collections.
- **Idempotency:** not required (OTP issuance is naturally idempotent within the resend window; verify is single-use server-side).
- **Rate limits (summary):** see per-endpoint above. All new limiters defined in `RouteServiceProvider` alongside the existing `otp-request`/`otp-verify`. Email-keyed (not phone-keyed). Settings-driven throttle (`OtpThrottle`) applies on top, keyed by email destination.
- **Headers honoured:** `Accept-Language` (locale for OTP email copy), `X-Request-Id` (auto), standard `X-RateLimit-*` + `Retry-After` on 429.
- **TTL / length / resend** come **only** from Settings (`otp.otp_ttl_seconds`, `otp_length`, `otp_resend_seconds`) — never hardcoded.

---

## 9. OpenAPI / Postman

After implementation, these endpoints are added to `docs/openapi/v1.extra.yaml` (tags: `Auth`, `Registration`) and the spec rebuilt (`node docs/openapi/build-openapi.mjs`) → they appear automatically in Swagger UI / ReDoc / Postman / Bruno (the live `/api/docs` system). No separate doc tooling needed.
