# FLUTTER — REGISTRATION & AUTH FLOW

> Client-side plan for the Flutter app. Backend contracts are in `API_SPEC.md`. Token storage, JWT lifetime (15 min access / 60 d refresh) and the refresh/rotate rules already exist server-side and are reused unchanged.

---

## 1. App state machine

```
              ┌─────────────┐
              │   Splash    │  read secure storage (refresh_token?)
              └──────┬──────┘
        refresh ok   │   no/expired token
        ┌────────────┴────────────┐
        ▼                         ▼
 ┌────────────┐            ┌──────────────┐
 │AUTHENTICATED│           │    GUEST     │
 │  (Home …)   │           │  (Welcome)   │
 └────────────┘            └──────┬───────┘
                                  │
                  ┌───────────────┼────────────────┐
                  ▼               ▼                 ▼
             ┌─────────┐    ┌──────────┐      (returning)
             │Register │    │  Login   │
             └────┬────┘    └────┬─────┘
                  │ 202          │ 202
                  ▼              ▼
             ┌────────────────────────┐
             │   Verify OTP (email)   │◀── Resend OTP (timer)
             └───────────┬────────────┘
                  200 (tokens) │
                              ▼
                       ┌────────────┐
                       │AUTHENTICATED│
                       └────────────┘
```

**Two top-level states:**
- **GUEST** — no valid refresh token. Can only reach: Welcome, Register, Login, Verify OTP, Resend, and the legal/help screens.
- **AUTHENTICATED** — valid tokens in secure storage. Full app (devices, subscriptions, orders, profile…). Access token auto-refreshes via the existing `/v1/auth/refresh`.

---

## 2. Screens

| Screen | State | Inputs / contents | Primary action → endpoint |
|---|---|---|---|
| **Splash** | — | logo; bootstraps the app in ONE request | guest → `GET /v1/bootstrap`; if a refresh_token is stored → `POST /auth/refresh` then `GET /v1/me` (one-shot: user + devices + subscriptions + flags) |
| **Welcome / Guest** | GUEST | brand, "Register" / "Login" | navigate |
| **Register** | GUEST | First Name, Last Name, Mobile (+994…), Email | `POST /auth/register` → Verify OTP |
| **Verify OTP** | GUEST | 6-digit code field, email shown masked, countdown (`expires_in_seconds`), Resend (disabled until `resend_available_in_seconds`) | `POST /auth/verify-email` → AUTHENTICATED |
| **Resend OTP** | GUEST | (action on Verify screen) | `POST /auth/resend-otp` |
| **Login** | GUEST | Email | `POST /auth/login` → Verify OTP |
| **Already Registered** | GUEST | shown if Register hints existing account; CTA "Go to Login" | navigate to Login |
| **Expired OTP** | GUEST | inline state on Verify when `otp_expired` | offer Resend |
| **Too Many Attempts** | GUEST | inline state on `otp_max_attempts` / `rate_limited` / `otp_temporarily_blocked`; show `Retry-After` countdown | block input until timer |
| **Forgot Password** | — | **Not applicable now** (passwordless). Becomes relevant only after a user sets a password (future Security feature) → reset via email OTP. Not built in this module. |
| **Home (Authenticated)** | AUTH | dashboard | reuses existing authed endpoints |
| **Profile / Security** | AUTH | view name/email; **future (designed-for, not built)**: "Set Password" → enables Email + Password login, "Change Password", "Change Email" | (future) `POST/PATCH /v1/me/password`, `PATCH /v1/me/email` |
| **Logout** | AUTH | confirm | `POST /auth/logout` → GUEST |

---

## 3. UI states per Verify screen (must all be handled)

| Server result | UI |
|---|---|
| 200 + tokens | store tokens (secure storage) → route to Home |
| 401 `wrong_code` | shake field, "Kod yanlışdır", keep attempts visible |
| 401 `otp_expired` | switch to **Expired OTP** state, enable Resend |
| 401 `otp_max_attempts` | **Too Many Attempts**, force Resend / back |
| 429 `rate_limited` / `otp_*` | **Too Many Attempts** with `Retry-After` countdown |
| 422 | field-level errors under inputs |
| network/5xx | retry banner |

---

## 4. Guest vs. Login-required — endpoint matrix

| Endpoint | Guest allowed? | Needs access token? |
|---|---|---|
| `GET /v1/bootstrap` | ✅ Guest | no (logged-out launch config) |
| `GET /v1/me` | ❌ | ✅ `auth:user` (one-shot authed bootstrap) |
| `POST /v1/auth/register` | ✅ Guest | no |
| `POST /v1/auth/verify-email` | ✅ Guest | no (issues tokens) |
| `POST /v1/auth/resend-otp` | ✅ Guest | no |
| `POST /v1/auth/login` | ✅ Guest | no |
| `POST /v1/auth/refresh` | ✅ Guest | no (refresh token in body) |
| `POST /v1/auth/logout` | ❌ | ✅ `auth:user` |
| `GET /v1/devices`, `/v1/orders`, `/v1/subscriptions`, `POST /v1/devices/{id}/open`, … | ❌ | ✅ `auth:user` |
| `GET /v1/health/*` | ✅ public | no |

Rule for the client: **only the bootstrap + auth endpoints + health are callable in GUEST state.** Everything else requires a stored access token and triggers the refresh-on-401 interceptor; a failed refresh drops the app to GUEST.

### Launch sequence (one-shot bootstrap)
- **Cold start, GUEST:** `GET /v1/bootstrap` → render gates from `data.app` (maintenance_mode → maintenance screen; client version < `min_version` or `force_update` → force-update screen), seed OTP timings + feature flags + support contacts. No login needed.
- **Cold start, has refresh_token:** `POST /v1/auth/refresh` → on success `GET /v1/me` (single request returning user + `registration_completed` + `email_verified` + `has_password` + `user_devices` + `active_subscriptions` + `feature_flags` + `app` gates + `unread_notifications_count` + `permissions`). **No second/third request.** On refresh failure → drop to GUEST + run the guest bootstrap.
- **Right after verify-email (auto-login):** the token envelope already carries `user`; call `GET /v1/me` once to hydrate the rest.
- **Extensibility:** `/v1/me` `data` grows new keys over time (apartments, vehicles, devices, complexes, notifications, invitations, visitor_passes, family_members, payments) — clients must ignore unknown keys and not assume a fixed key set.

---

## 5. Token handling (reuse — no change)

- Store `refresh_token` in **secure storage** (Keychain/Keystore); keep `access_token` in memory.
- Attach `Authorization: Bearer <access>` to authed calls.
- On `401` for an authed call → call `/auth/refresh` once; on success retry; on failure → clear storage → GUEST.
- `device.install_uuid` is generated once per install and reused for every `verify-email` (binds refresh tokens to the install, as today).
- Logout → `POST /auth/logout` then clear storage.

---

## 6. Copy / i18n

OTP email language follows `Accept-Language` (az default). In-app strings (az/ru/en) for: register form labels, OTP entry, countdown ("Kod {n} dəqiqə etibarlıdır"), resend ("Yenidən göndər ({n}s)"), error states above. No secrets/codes ever logged client-side.
