# SCREEN FLOW

> Planning only. Every screen with: purpose · key state(s) · primary action → backend endpoint. Grouped by module. Guest screens need no token; Auth screens require a session. Every data screen renders `loading/data/empty/error` per the design system.

---

## 0. App-state gate (runs before any screen)

```
Splash → fetch GET /v1/bootstrap
  ├─ maintenance_mode = true ─────────────▶ Maintenance
  ├─ client version < min_version OR force_update ▶ Force-Update
  ├─ no connectivity ─────────────────────▶ Offline (cached bootstrap if any)
  └─ ok →
        ├─ refresh_token stored → POST /auth/refresh → GET /v1/me → Home (AUTHENTICATED)
        └─ no/expired token → Welcome (GUEST)
```

---

## 1. System / shell screens

| Screen | State | Purpose | Action |
|---|---|---|---|
| **Splash** | — | Brand + run the launch gate (§0). | auto → bootstrap/refresh |
| **Onboarding** | guest, first-run only | 2–3 intro slides; persisted "seen" flag (prefs). | → Welcome |
| **Welcome / Guest landing** | guest | Brand + "Register" / "Login". | navigate |
| **Offline** | any | Shown when no connectivity on a network-required action; retry. | retry → re-run last intent |
| **Maintenance** | any | `app.maintenance_mode` on; message + support contact. | poll `/bootstrap` / retry |
| **Force-Update** | any | client < `min_version` or `force_update`; store link, no dismiss. | open store (`url_launcher`) |

---

## 2. Authentication / Registration (GUEST)

| Screen | State | Purpose | Action → endpoint |
|---|---|---|---|
| **Register** | guest | Collect First Name, Last Name, Mobile (+994…), Email. | `POST /v1/auth/register` → Verify OTP |
| **Login** | guest | Collect Email. | `POST /v1/auth/login` → Verify OTP |
| **Verify OTP** | guest | 6-box OTP, countdown (`meta.expires_in_seconds`), resend (disabled until `resend_available_in_seconds`). Used by BOTH register + login. | `POST /v1/auth/verify-email` → AUTHENTICATED (Home) |
| **Resend OTP** | guest (action on Verify) | Re-issue the code; respects the resend timer + rate-limit. | `POST /v1/auth/resend-otp` |
| **Already Registered** | guest | Surfaced when register returns 409 `email_already_registered`; CTA → Login. | navigate → Login |
| **Inline states on Verify** | guest | `wrong_code` (shake), `otp_expired` (→ resend), `otp_max_attempts` / 429 `rate_limited` (countdown lock). | per error map |

**Forgot Password** — **not applicable in v1** (passwordless; password is a Future Security feature). Placeholder only.

---

## 3. Home (AUTHENTICATED)

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **Home / Dashboard** | authed | Primary device(s) + quick **Open**, active subscription chip, alerts (expiry/maintenance). Bottom-nav root. | `GET /v1/me` (cached) + `GET /v1/devices` |

---

## 4. Devices

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **My Devices** | authed | List devices (serial, online/offline, status). Pull-to-refresh, paginated. | `GET /v1/devices` |
| **Device Detail** | authed | Status, online state, owner/roster (read), stats entry, **Open** button. | `GET /v1/devices/{id}`, `/devices/{id}/stats` |
| **Device Stats** | authed | Usage/online history (`?period`). | `GET /v1/devices/{id}/stats` |

---

## 5. Barrier Open (core flow)

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **Open (sheet/screen)** | authed | Big "Open" CTA; biometric gate (if enrolled); shows live state. | `POST /v1/devices/{id}/open` |
| **Opening (live)** | authed | State machine UI: `queued → dispatching → dispatched → opened/failed/expired`. Polls the command. | `GET /v1/commands/{id}` (poll) |
| **Open result** | authed | Success (opened) / failure (reason) / offline / cooldown (429) / expired; optional feedback. | `POST /v1/commands/{id}/feedback` |
| **Open History** | authed | Recent open commands + outcomes. | `GET /v1/devices/{id}/commands` |

> Flow: Open → (biometric) → POST open returns `command_id` + expected timing → poll `/commands/{id}` until terminal → render result. Handle 429 (cooldown/rate-limit) and the device-offline path explicitly.

---

## 6. Orders / Payments / Checkout

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **Orders** | authed | Order history (reference, amount, status). Paginated. | `GET /v1/orders` |
| **Order Detail** | authed | Items, status, timeline, pay/recheck. | `GET /v1/orders/{id}` |
| **Checkout** | authed | Create order → open BirPay **hosted checkout** `confirmUrl` in an in-app webview; handle return. | `POST /v1/orders` → webview → `GET /v1/payments/return` |
| **Payment Result** | authed | Verify final state after return (don't trust the redirect — recheck). | `POST /v1/orders/{id}/recheck` |

> Payments are **redirect-based** (BirPay hosted page). The app never handles card data. After the webview returns, always `recheck` to get the authoritative status.

---

## 7. Subscriptions

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **Subscriptions** | authed | List with status + days remaining. | `GET /v1/subscriptions` |
| **Subscription Detail** | authed | Tier, period, renew, auto-renew toggle. | `GET /v1/subscriptions/{id}` |
| **Renew** | authed | Start a renewal order (→ Checkout). | `POST /v1/subscriptions/{id}/renew` |
| (auto-renew) | authed (toggle) | Enable/disable auto-renew. | `PATCH /v1/subscriptions/{id}/auto-renew` |

---

## 8. Profile / Security / Settings

| Screen | State | Purpose | Endpoint |
|---|---|---|---|
| **Profile** | authed | Name, email (verified badge), phone, locale, avatar (reserved), devices list. | `GET /v1/me` |
| **Edit Profile** | authed | Edit name/locale. *(No mobile profile-update endpoint yet → flag edit; view-only until backend ships it.)* | — (planned) |
| **Security** | authed | Biometric app-lock toggle, barrier biometric gate. | `POST /v1/me/biometrics/enroll`, `DELETE /v1/me/biometrics` |
| **Set Password** | authed | **Future** — set a password (`has_password=false`). Hidden until the backend Security feature ships. | — (future) |
| **Change Password** | authed | **Future** — change password. Hidden until shipped. | — (future) |
| **Settings** | authed | Theme (light/dark/system), language (az/en/ru), notification prefs (local). | local |
| **Support** | any | Support email/phone (from bootstrap), FAQ, contact. | `GET /v1/bootstrap` |
| **About** | any | Version, build, legal, licenses. | local |

---

## 9. Notifications (Future)

| Screen | State | Purpose |
|---|---|---|
| **Inbox** | authed (flagged off) | In-app notification list — **shell only** until backend notifications ship; FCM message handler built but token registration gated. |

> **Planned MVP behaviour (Reconciliation §C; [NOTIFICATIONS_MOBILE_FLOW.md](../notifications/NOTIFICATIONS_MOBILE_FLOW.md)):** when notifications ship, the Inbox becomes a real cursor-paginated list (unread/read · category · timestamp · title/body · tap→deep-link) backed by `GET /v1/notifications` + `POST /v1/notifications/{id}/read`, with a single shared unread badge (app-bar bell + Profile row). **Deep-link:** a push carries `data:{type, notification_id, ids}`; tap routes by `type` to an existing screen (visitor→device detail, subscription→Active Subscriptions, device→device detail, system→Inbox) and fetches detail authenticated. Foreground shows a local notification; background/terminated display from the FCM `notification` block. Still gated behind `FeatureFlag.push`.

---

## 10. Screen-state matrix (every data screen)

| Condition | UI |
|---|---|
| loading | skeleton (list/card shimmer) |
| data | content |
| empty | `EmptyState` (icon + message + CTA) |
| error (4xx/5xx/offline) | `ErrorState` (message + retry), see `API_INTEGRATION.md` error map |
| unauthenticated (401 after refresh fail) | bounce to Welcome (GUEST) |

---

## 11. Guest vs Authenticated routes

- **Guest-only:** Splash, Onboarding, Welcome, Register, Login, Verify OTP. (+ system: Offline/Maintenance/Force-Update reachable from any state.)
- **Authenticated-only:** Home, Devices, Device Detail, Barrier Open, Orders, Checkout, Subscriptions, Profile, Security, Settings, Notifications.
- **Either:** Support, About, system states.

Guard logic + redirects: see `NAVIGATION.md`.
