# FLUTTER MODULES

> Planning only. Every module: responsibility, the backend endpoints it consumes, and its v1 status (**Live** = backend ready · **Flagged** = built but gated off until the backend ships · **Future** = post-v1).

---

## Module list

| # | Module | Status | Responsibility | Backend endpoints |
|---|---|---|---|---|
| 1 | **Bootstrap** | Live | Launch orchestration: fetch guest config, gate maintenance/force-update, then (if session) fetch `/me`. | `GET /v1/bootstrap`, `GET /v1/me`, `GET /v1/health/ready` |
| 2 | **Authentication** | Live | Session lifecycle: token storage, attach bearer, refresh-on-401 (single-flight), logout, auth-state stream. | `POST /v1/auth/refresh`, `/auth/logout` |
| 3 | **Registration** | Live | Email-OTP sign-up: collect name/phone/email → OTP → verify → auto-login. Duplicate/verified handling. | `POST /v1/auth/register`, `/verify-email`, `/resend-otp` |
| 4 | **Login** | Live | Returning user email-OTP login (shares the verify flow with Registration). | `POST /v1/auth/login`, `/verify-email` |
| 5 | **Home / Dashboard** | Live | Post-login landing: device summary, active subscription, quick "open", alerts from `/me`. | `GET /v1/me`, `GET /v1/devices` |
| 6 | **Devices** (granular — see below) | Live | Split into 5 sub-features, **each with its own Repository + Provider + ViewModel** to minimise rebuilds. | `GET /v1/devices`, `/devices/{id}`, `/devices/{id}/stats`, `/devices/{id}/commands` |
| 7 | **Barrier Open** (`device_open`) | Live | The core action: request open → poll command → show actuation result. Biometric gate + cooldown. | `POST /v1/devices/{id}/open`, `GET /v1/commands/{id}`, `POST /v1/commands/{id}/feedback` |
| 8 | **Orders / Checkout** | Live | Create order → open BirPay hosted checkout → return → recheck status; order history. | `GET/POST /v1/orders`, `/orders/{id}`, `/orders/{id}/recheck`, `GET /v1/payments/return` |
| 9 | **Subscriptions** | Live | List + detail, renew, toggle auto-renew, expiry reminders surfaced from `/me`. | `GET /v1/subscriptions`, `/subscriptions/{id}`, `POST /renew`, `PATCH /auto-renew` |
| 10 | **Profile** | Live | View/edit name, email, phone display, avatar (reserved), locale, devices list. | `GET /v1/me` (+ profile-update endpoint is **not** implemented → view-only for fields the API can't yet PATCH; flag edit) |
| 11 | **Security** | Partial | Biometric app-lock + barrier-open biometric gate (Live). Set/Change password (**Future** — `has_password`, backend not built). | `POST /v1/me/biometrics/enroll`, `DELETE /v1/me/biometrics` |
| 12 | **Settings** | Live | Theme (light/dark/system), language (az/en/ru), notifications prefs (local), about. | local only (+ bootstrap public settings) |
| 13 | **Notifications** | Future/Flagged | In-app inbox + push receipt. Backend notifications + push-token endpoint **not implemented** → ship the FCM handler + inbox shell flagged off; `unread_notifications_count` reads 0. | (none yet — plan `notifications/*` + `push-token` contract) |
| 14 | **Support** | Live | Support contacts (email/phone from bootstrap), FAQ (static/local), contact actions. | `GET /v1/bootstrap` (support block) |
| 15 | **About** | Live | App version, legal, licenses, build info. | `package_info` (local) |
| 16 | **System states** | Live | Cross-cutting screens: Splash, Offline, Maintenance, Force-Update — driven by connectivity + bootstrap gates. | `GET /v1/bootstrap` |

---

## Devices module — granular breakdown

The Devices area is split into independent sub-features so a change in one (e.g. live command polling) does **not** rebuild the others. Each owns its repository, provider(s), and ViewModel; they share only the `Device` domain entity + the cache.

| Sub-feature | Folder | Responsibility | Repository | Provider / ViewModel | Endpoint |
|---|---|---|---|---|---|
| **Device List** | `devices/device_list/` | Paginated list, online/offline, pull-to-refresh, search | `DeviceListRepository` | `deviceListProvider` (AsyncNotifier, paginated) | `GET /v1/devices` |
| **Device Detail** | `devices/device_detail/` | Single device: status, owner/roster (read), entry points | `DeviceDetailRepository` | `deviceDetailProvider(id)` (family, autoDispose) | `GET /v1/devices/{id}` |
| **Device Stats** | `devices/device_stats/` | Usage/online history by period | `DeviceStatsRepository` | `deviceStatsProvider(id, period)` (family) | `GET /v1/devices/{id}/stats` |
| **Device History** | `devices/device_history/` | Past open commands + outcomes (paginated) | `DeviceHistoryRepository` | `deviceHistoryProvider(id)` (paginated) | `GET /v1/devices/{id}/commands` |
| **Device Commands** | `devices/device_commands/` | Live command state for a single command (poll) | `CommandRepository` | `commandStatusProvider(commandId)` (poll loop) | `GET /v1/commands/{id}` |
| **Device Open** (Barrier) | `barrier/device_open/` | The open action + biometric gate + feedback | `BarrierRepository` | `barrierOpenProvider(deviceId)` (Notifier) | `POST /open`, `POST /commands/{id}/feedback` |

**Rebuild discipline:**
- Each sub-feature provider is **`autoDispose` + `family`** (by device/command id) → state is scoped and freed on pop.
- The Detail screen's tabs (Overview / Stats / History / Commands) each watch **their own** provider — opening "Stats" doesn't rebuild the history list.
- The live **Device Open / Commands** poll loop holds its own `barrierOpenProvider`/`commandStatusProvider`; its frequent updates never touch the Detail/List/Stats providers.
- Cross-feature reads (e.g. the device entity) come from the shared cache via `DeviceRepository`, so the list and detail stay consistent without coupling their ViewModels.
- **No "god provider":** there is no single `devicesProvider` holding list+detail+stats+history+open; that would rebuild everything on any change. (See `STATE_MANAGEMENT.md §granular providers`.)

---

## Module dependency map

```
Bootstrap ──┬─▶ Authentication ──▶ (all authed modules)
            └─▶ System states (maintenance/force-update/offline)

Authentication ──▶ Home ──┬─▶ Devices ──▶ Barrier Open
                          ├─▶ Orders/Checkout ──▶ Subscriptions
                          ├─▶ Profile ──▶ Security
                          ├─▶ Notifications (future)
                          └─▶ Settings / Support / About
```

- **Bootstrap** + **Authentication** are infrastructure (every feature depends on them).
- **Registration** + **Login** converge on the same `verify-email` flow (one shared verify ViewModel/UseCase — no duplication, mirrors the backend's single verify endpoint).
- **Barrier Open** is the highest-value feature and the most state-heavy (async polling) — gets the most design + test attention.
- **Notifications** is the only fully-Future module; everything else is Live or partially Live.

---

## Module → feature-folder mapping

Each module = one `features/<name>/` folder with `data/ domain/ presentation/`. Shared concerns (design system, network, storage, routing, l10n) live outside `features/` (see `FLUTTER_ARCHITECTURE.md §3`). `Registration` + `Login` live under a single `features/auth/` folder (shared verify), with separate screens.

---

## What is explicitly OUT of scope for the mobile app (admin-only / unimplemented)

Residents directory, Complex management, Refund issuance, Admin RBAC, Settings administration, Payment logs, Audit, Reports, User management — these are **admin-panel** concerns (the React `admin-ui`), not the mobile app. The Flutter app never calls `/admin/v1/*`. Notifications + invitations + privacy/consents are **unimplemented design endpoints** — not built on mobile until the backend ships them (planned, flagged off).
