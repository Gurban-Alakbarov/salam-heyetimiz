# Salam Həyətimiz — Frontend Implementation Plan

**Covers:** the Flutter mobile app **and** the web Admin Panel.
**Status:** Planning baseline. Buildable scope is pinned to the **currently deployed** backend.
**Verified against:** `docs/_api_ground_truth.md` (deployed `route:list`, 2026-06-21), `docs/openapi/v1.yaml` (v1.2.0), `docs/UI_UX_SPECIFICATION.md` (v1.1), `docs/PROJECT_CONSTITUTION.md` (v1.2).
**Defers API detail to:** [`FLUTTER_API_INTEGRATION.md`](FLUTTER_API_INTEGRATION.md) (mobile) and [`ADMIN_API_INTEGRATION.md`](ADMIN_API_INTEGRATION.md) (admin). This plan references those guides for request/response shapes rather than duplicating them.

> **Legend (status column, every screen table):**
> ✅ **buildable now** — every endpoint it consumes is deployed.
> ⛔ **blocked on backend (planned batch)** — needs an endpoint that exists in OpenAPI but is **not** in the deployed route table. Gate behind a feature flag; do not call from production builds.
> 🟡 **partial** — core path is buildable; a secondary feature on the screen is blocked.

---

## 1. Goals & Scope

### 1.1 Product north star

The core feature of Salam Həyətimiz is **opening a gate from your phone**. Everything else (auth, subscriptions, payments, admin ops) exists to make that one tap reliable, paid-for, and supportable. The MVP is "the right people can open the right gates, and we can bill and support them," not "feature parity with the full spec."

### 1.2 Locked product rules the frontend must honour

| Rule | Source | Frontend obligation |
|---|---|---|
| **Open permission** is checked **server-side at every open**: active subscription **AND** on roster **AND** device not `disabled` **AND** not in cooldown. | R-DOM-05 | Never gate opens purely client-side. Render `can_open` / `suspension_reason` from the API; the server is the authority. There is **no offline local open** (BLE is deferred off the MVP critical path). |
| **Biometric unlock required before every open command** (Face ID / Touch ID / Android BiometricPrompt, PIN fallback). | R-SEC-04 | The Open button triggers a local biometric prompt *before* `openDevice` is called. |
| **Real-time push (Reverb/WebSocket) is DEFERRED.** 1 s polling of `getCommand` is a **launch acceptance criterion**. | R-ARCH-12 | The open-flow UI drives state from `getCommand` polling, not the `websocket_channel` field. Build the polling path first; treat WS as a later optimisation. |
| **Subscription gating** drives device-detail copy and CTAs. | R-DOM-04/05/06 | `suspension_reason` (`none` / `subscription_expired` / `owner_sub_expired_others_active` / `device_disabled` / `device_suspended`) selects the device-detail state and CTA. |
| **Localization: `az` default, plus `ru` + `en`.** API returns `error.code` + `message_key`; clients resolve their own bundles. The deployed error envelope *also* carries a localized `message` (safe to show today), but the **contract** is the key. | R-LOC-01/02/05 | Branch logic on `error.code` / `message_key` — **never on `message`**. Keep `az/ru/en` bundles; show `message` only as a fallback string. Money from `amount_minor ÷ 100`, `₼`; 24-h time. |
| **Money & time conventions.** | R-LOC-07 | Money is integer minor units (qəpik); render `÷ 100` as `12,00 ₼`. Time is UTC RFC-3339 → display local, 24-hour, `DD.MM.YYYY` (az/ru) / `DD/MM/YYYY` (en). |

### 1.3 MVP definition (launchable against the deployed backend)

The MVP is the set of ✅ screens across both surfaces:

- **Mobile MVP:** OTP onboarding → biometric enroll → device list → **device detail + the open-gate flow** → subscriptions list/detail + auto-renew toggle → orders/checkout/Kapital WebView/result → renew → technical mode (register + assign + test open).
- **Admin MVP:** two-phase login + 2FA → device list/detail/create/edit/disable/enable/transfer/decommission → command history + diagnostics + whitelist queue + resync → orders list/detail/recheck/refund → refunds list → subscriptions list.

### 1.4 Explicitly out of MVP (blocked on backend batches)

Roster/invitations, full Profile (`GET/PATCH/DELETE /me`), Notifications + push-token register, Privacy/consents/export/deletion, technical diagnostics ping — on **mobile**. Dashboard, Users (customers), Admins, Lookups, Reports, Audit, Settings, Feature Flags, Notification Templates — on **admin**. These are designed and stubbed (feature-flagged) so they can be switched on when their backend batch deploys, but no production flow calls them.

---

## 2. Mobile App (Flutter)

### 2.1 Recommended stack

| Concern | Choice | Justification |
|---|---|---|
| Framework | **Flutter** (stable channel) | Single codebase iOS + Android; required by the spec; mature biometrics, secure storage, WebView plugins. |
| **State management** | **Riverpod** (v2, `flutter_riverpod` + `riverpod_generator`) | Chosen over Bloc. The app is read-heavy with a few high-stakes mutations (open, pay). Riverpod's `AsyncValue` models the **six required states** (idle/loading/data/empty/error + offline) directly, `ref.invalidate` gives clean pull-to-refresh + cache, and `family` providers fit per-device/per-order detail. It needs less boilerplate than Bloc for this surface area while staying testable (override providers in tests). Bloc's event-stream ceremony only pays off with complex long-lived state machines; here the one real state machine (the open-flow lifecycle) is small enough to model as a dedicated `StateNotifier`. **One exception:** the open-flow controller is implemented as an explicit `StateNotifier`/state machine because its lifecycle (`queued→dispatching→dispatched/opened/failed/expired/timedOut`) deserves a first-class, unit-testable transition table. |
| HTTP | **dio** `^5.4` | Interceptors for bearer injection, single-flight 401 refresh, and `DioException → ApiException` mapping — exactly the pattern in `FLUTTER_API_INTEGRATION.md` §2. |
| Secure storage | **flutter_secure_storage** `^9` | Keychain/Keystore for access + refresh tokens and the stable `install_uuid`. |
| Routing | **go_router** `^14` | Declarative routes + deep links (`salam://payment/return`, future `salam://invite/{token}`); redirect guards keyed on auth state (Riverpod listenable). |
| Localization | **flutter_localizations + intl** (ARB: `intl_az.arb` default, `intl_ru.arb`, `intl_en.arb`) | R-LOC-03 mandates compiled ARB (no OTA in MVP). ICU plurals (Russian's four forms), named placeholders. `errors.*` keys map 1:1 to API `error.code`. |
| Payments WebView | **webview_flutter** `^4` | Hosts the Kapital 3DS page; intercepts the custom-scheme `return_url`. (`url_launcher` as external-browser fallback.) |
| Biometrics | **local_auth** | Native Face ID / Touch ID / BiometricPrompt before every open (R-SEC-04). |
| Helpers | **uuid** (install_uuid + per-call `Idempotency-Key`), **package_info_plus**, **device_info_plus**, **connectivity_plus** (offline banner) | Per the integration guide. |
| Push (later) | **firebase_messaging** (FCM) | Wired but **dormant** — backend Notifications + push-token register are not deployed. Token captured and held; registration call gated by a feature flag until the batch ships. |

### 2.2 App architecture (feature-first, layered)

Feature-first folders, each internally layered **data → domain → presentation**. Cross-cutting concerns live in `core/`.

```
lib/
  core/
    network/        dio_client.dart, auth_interceptor.dart, api_exception.dart, idempotency.dart
    storage/        secure_store.dart (tokens + install_uuid)
    error/          failure.dart, error_mapper.dart (error.code → localized key)
    router/         app_router.dart (go_router), guards.dart
    l10n/           generated AppLocalizations
    widgets/        DeviceCard, StatusPill, MoneyText, OtpField, PhoneField, EmptyState, ErrorState, OfflineBanner
    theme/          tokens, light/dark
    state/          page<T> (cursor pagination), async_ext (AsyncValue → 6 states)
  features/
    auth/           data (otp/refresh/biometrics) · domain (User, AuthTokens) · presentation (S-04/05/06/08)
    devices/        data (list/detail/stats) · domain (Device, DeviceDetail, DeviceStats) · presentation (S-10/11/13/14)
    open/           domain (OpenCommand state machine) · presentation (S-12 open sheet)  ← the core flow
    subscriptions/  data · domain (Subscription, periods) · presentation (S-30/31)
    orders/         data (create/get/recheck/list) · domain (Order) · presentation (S-32/33/34)
    technical/      data (register/assign) · presentation (T-01..T-06)
    profile/        ⛔ stubbed behind flag (needs GET/PATCH /me)
    roster/         ⛔ stubbed behind flag (needs roster/invitations)
    notifications/  ⛔ stubbed behind flag (needs notifications + push register)
    privacy/        ⛔ stubbed behind flag (needs consents/export/deletion)
  app.dart, main.dart
```

**Layering rules:** presentation depends on domain; data implements domain repository interfaces; domain has no Flutter/dio imports. Repositories return domain models or throw `ApiException`; providers expose `AsyncValue<T>`. DTO ↔ domain mapping lives in `data`.

### 2.3 Screen-by-screen → endpoints → status

#### Boot, onboarding, auth (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| S-01 Splash | Read stored auth, validate/refresh, route | `getHealthLive` (best-effort), `refreshToken` (if refresh token present) | ✅ |
| S-02 Locale picker (first run) | Pick `az`/`ru`/`en` before anything else | none (local; later synced via `PATCH /me` ⛔) | ✅ |
| S-03 Onboarding (3 slides) | Communicate value props | none | ✅ |
| S-04 Phone entry | Collect `+994` phone, send OTP | `requestOtp` | ✅ |
| S-05 OTP entry | Verify code, obtain tokens | `verifyOtp` (persists tokens; captures `install_uuid`) | ✅ |
| S-06 Profile complete (name/locale) | Capture display name on first login | `updateMe` ⛔ | ⛔ (defer name capture; allow null `full_name` and skip to home until `PATCH /me` ships) |
| S-07 Consents | Terms/Privacy/marketing consent | `recordConsent` ⛔ | ⛔ (show static Terms/Privacy links inline at S-04; full consent ledger blocked) |
| S-08 Biometric enroll | Opt-in to biometric unlock | `enrollBiometrics`, `disableBiometrics` | ✅ |

**Notes:** OTP rate limits drive UX — `requestOtp` 3/phone/10min + 30/IP/hr; `verifyOtp` 10/phone/10min. On `429` show `Retry-After` countdown on Resend. On `verifyOtp` errors branch on `wrong_code` / `otp_expired` / `otp_max_attempts`. Since `S-06`/`S-07` are blocked, the MVP onboarding is **S-04 → S-05 → S-08 → S-10**; name/consents are deferred and surfaced when their endpoints land.

#### Home & devices (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| S-10 Home / Devices | List devices the user can open/owns | `listMyDevices` (`?filter=all\|owned\|member\|suspended`) | ✅ |
| S-11 Device detail | Detail + primary Open action | `getDevice`, `getDeviceStats`, `listDeviceCommands` (last 10) | ✅ |
| S-13 Device history | Every open attempt by this user | `listDeviceCommands` (cursor, `state`/`since`/`until`) | ✅ |
| S-14 Device stats | Usage chart + KPIs | `getDeviceStats` (`?period=7d\|30d\|90d`) + drill-down `listDeviceCommands` | ✅ |
| S-15 Roster (owner) | Users with access | `listDeviceRoster`, `listDeviceInvitations` ⛔ | ⛔ |
| S-16 Invite user | Send invitation | `createInvitation` ⛔ | ⛔ |
| S-17 Roster user detail | Revoke access | `revokeRosterUser` ⛔ | ⛔ |
| S-18 Invitation accept | Accept/decline invite (deep link) | `getInvitationByToken`, `acceptInvitation`, `declineInvitation` ⛔ | ⛔ |

**S-11 gating:** render from `can_open` + `suspension_reason` + `cooldown_seconds_remaining`. The "Users" tab and any roster affordance are hidden until the roster batch deploys. CTA flips to "Yenilə" (Renew) when `suspension_reason == subscription_expired`; shows owner-only copy for `owner_sub_expired_others_active`; shows a Help link for `device_disabled`.

#### THE OPEN-GATE SCREEN — S-12 (✅, the core flow)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| S-12 Open flow (sheet) | Live feedback through the open lifecycle | `openDevice` (emit), `getCommand` (poll), `submitOpenFeedback` (conditional) | ✅ |

This is the product. Implement it first and most carefully.

**UX & control flow:**
1. **Big Open button** on S-11 (circular, brand-primary, idle pulse loop). Disabled with an explicit reason when `can_open == false` (subscription expired → CTA becomes Renew; device disabled → Help link; cooldown → see step 5).
2. **Biometric gate (R-SEC-04):** tapping Open triggers `local_auth` *before* any network call. Cancel/no-hardware → toast, abort.
3. **Emit open:** `POST /devices/{id}/open` with a **fresh `Idempotency-Key`** (UUID v4) per distinct user intent — required by the server. Persist the key with the in-flight attempt so a network retry reuses it (no double-dispatch). Response `202` gives `command_id`, `expected_completion_ms`, `driver_confirms_actuation`, `websocket_channel`.
4. **Open sheet** rises (~40% viewport): circular progress arc sized to `expected_completion_ms`, state label, device name, Cancel (only while `queued`).
5. **Cooldown / rate-limit (429):** `openDevice` may return `429 cooldown` or `429 rate_limited` with `Retry-After`. Do **not** open the sheet — instead replace the Open button label with a **live countdown from `Retry-After`** (seconds ticking down); re-enable at zero. Rate-limit shows a toast with the wait. (Limits: 12/user/min, 4/device/min.)
6. **Live state via polling (Reverb deferred):** poll `GET /commands/{commandId}` ~once per second until a **terminal** state (`opened`, `dispatched`, `failed`, `expired`) or a client-side timeout (default 30 s → `timedOut`). The `websocket_channel` field is ignored in MVP (WS is a later optimisation; polling is the launch criterion). Transient 5xx during polling → keep polling; `404` → real error; `429` → back off `Retry-After` then resume.
7. **Success / fail animation (driver-aware):**
   - `opened` **and** `driver_confirms_actuation == true` → arc turns green, check icon, **medium-impact success haptic**, toast **"Açıldı"** (we have actuation evidence — Traccar output read-back).
   - `dispatched`, **or** `opened` with `driver_confirms_actuation == false` → arc green, **paper-plane icon**, soft haptic, toast **"Göndərildi"** (sent, actuation unconfirmed). Optionally prompt **"Qapı açıldı?"** Yes/No → `submitOpenFeedback {gate_moved}` (builds per-device reliability metrics; idempotent, one per command/user).
   - `failed` → arc red, heavy-impact error haptic, show `failure_reason`, "Yenidən cəhd et".
   - `expired` → ask the user to retry. `timedOut` → keep a spinner / offer manual re-check via `getCommand`.
8. **Errors before dispatch:** `403 subscription_required` → route to renew; `403 device_disabled` → disabled state + Help; `403 forbidden` → not on roster/not payer; `502 device_offline` → offer retry.

The open-flow lives in `features/open/` as an explicit `StateNotifier` with a unit-testable transition table; the Dart poll loop in `FLUTTER_API_INTEGRATION.md` §6.4 is the reference implementation.

#### Subscriptions (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| S-30 Subscriptions | List across all devices | `listMySubscriptions` (`?status=`) | ✅ |
| S-31 Subscription detail | Detail + renew + auto-renew toggle | `getSubscription`, `renewSubscription` (Idempotency-Key **required**), `toggleAutoRenew` | ✅ 🟡 |

**Auto-renew nuance:** `toggleAutoRenew` is deployed, but **enabling may `409`** because Kapital tokenization isn't live. Render the toggle, and on `409` show "not available yet" and revert — don't treat it as an error. `renewSubscription` returns an `Order` with `bank_redirect_url` → hand off to the payment flow (S-33).

#### Orders & payments (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| S-32 Checkout | Confirm charge, create order | `createOrder` (Idempotency-Key **required**) | ✅ |
| S-33 Payment WebView | Host Kapital 3DS, capture return | none directly (intercepts `salam://payment/return`) | ✅ |
| S-34 Payment result | Resolve authoritative status | `getOrder`, `recheckOrder` (if still pending/authorising) | ✅ |
| S-35* (orders list, optional) | History of the user's orders | `listMyOrders` (`?status=`) | ✅ |

**Payment flow:** `createOrder` (persist the Idempotency-Key with the pending order so retries reuse it) → open `bank_redirect_url` in `webview_flutter` → intercept the custom-scheme return → S-34 calls `getOrder`; if still `pending`/`authorising`, force `recheckOrder`. The `orderId` in the return URL is a **hint only** — verify the caller is the payer before rendering detail; if `getOrder` resolves indeterminate after ~10 s, show "status pending, we'll notify you" (note: the push that would confirm later is itself blocked until Notifications deploys — until then, the recheck button is the recovery path). Handle `503 payment_provider_unavailable` (Kapital degraded) with retry-later.

#### Technical mode (✅ register + assign; ⛔ diagnostics ping)

Activated by an **admin JWT** (`technical` or `super_admin`) authenticating the mobile app. Replaces the bottom nav with a single Devices entry.

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| T-01 Tech login | Admin login + 2FA inside mobile | `adminLogin`, `adminVerify2fa` | ✅ |
| T-02 Scan QR | Seed registration from device label | none (camera) | ✅ |
| T-03 Device register | Create the device record | `techRegisterDevice` (`DeviceTechRegister`) | ✅ |
| T-04 Diagnostics ping | Verify connectivity before assign | `techDiagnosticsPing` ⛔ | ⛔ (skip ping in MVP; proceed to assign, or rely on a later test open) |
| T-05 Assign owner | Bind device to owner by phone | `techAssignDevice` | ✅ |
| T-06 Activation summary | Recap + test open | `openDevice`, `getCommand` | ✅ |

**Note:** the diagnostics ping endpoint is not deployed, so T-04 is blocked. MVP technical flow is **T-01 → T-02 → T-03 → T-05 → T-06 (test open)**; the test open at T-06 doubles as the connectivity check.

#### System screens (✅)

S-90 Generic error (with `request_id`), S-91 Maintenance (reads a feature flag; flag source TBD until Settings/Flags deploy — hard-code/env for MVP), S-92 Update required (app-config at boot), S-93 Offline banner (`connectivity_plus`). All ✅ except the flag *source* for S-91/S-92, which is env-driven until admin Feature Flags ship.

### 2.4 Cross-cutting (mobile)

- **Auth / token refresh:** dio `AuthInterceptor` with a **single-flight lock** — concurrent 401s trigger exactly one `refreshToken`; refresh tokens **rotate**, so always persist the new pair. Refresh failure (revoked/replayed → 401) clears tokens and routes to S-04. Don't run the interceptor on `/auth/*` calls. (Pattern: `FLUTTER_API_INTEGRATION.md` §4.2.)
- **Error handling:** one `ApiException` mapper. Branch on `error.code` / `message_key` for logic and navigation; show `error.message` only as the user-facing fallback string; log `request_id`. `422` maps `error.fields` to form fields. Localized `errors.*` keys mirror API codes 1:1.
- **The six states:** every API-bound screen defines idle / loading (skeletons for lists, spinners for actions) / empty (illustration + explainer + optional CTA) / error (`ErrorState` + retry) / success / offline (top banner + cached reads; mutating actions disabled). Pull-to-refresh via `ref.invalidate`.
- **Optimistic vs pessimistic:** optimistic only for low-stakes toggles (biometric flag). **Pessimistic (wait for server ack)** for **open, pay, renew** — never optimistic.
- **Pagination:** infinite scroll over opaque cursors (`page.next_cursor`); load next within 200 px of bottom.
- **Push (FCM):** integrate `firebase_messaging`, capture the token, but **hold registration** — backend Notifications + push-token register are not deployed. Behind a feature flag; flip on when the batch lands. Until then, payment-result "we'll notify you" and renewal reminders won't arrive — the recheck button and the subscriptions screen are the user's recovery paths, and this limitation is called out in §6 risks.
- **Privacy in UI:** `FLAG_SECURE` (Android) / hide-on-background (iOS) on payment/subscription screens; mask phones outside self-profile (`+994 50 *** 45 67`); show only `**** 1234` for cards.

---

## 3. Admin Panel (Web)

### 3.1 Recommended stack

| Concern | Choice | Justification |
|---|---|---|
| Framework | **React 18 + TypeScript** (Vite) | Chosen over Vue. The admin surface is table/CRUD-heavy with role-gated nav and many list+filter+detail+modal flows; React's ecosystem for **enterprise data grids** and **server-state** is the strongest fit, and TypeScript lets us model the API enums (`status`, `purpose`, `role`, command/refund states) as discriminated unions so role-gated actions and state-driven UI are compiler-checked. Vue would work; React wins on data-grid maturity and the team-availability default. |
| Server state | **TanStack Query (react-query) v5** | Every screen is "fetch a cursor-paginated list / fetch a detail / mutate + invalidate." react-query gives caching, background refetch, `useInfiniteQuery` for cursors, optimistic updates with rollback, and request dedup — exactly the admin pattern. |
| HTTP | **axios** instance | Base URL `…/admin/v1`, request interceptor injects the in-memory bearer + default `Accept-Language: az`, response interceptor force-logs-out on `401` (no admin refresh token). Pattern: `ADMIN_API_INTEGRATION.md` §2. |
| Data grid / table | **TanStack Table** (headless) + a styled wrapper, or **MUI DataGrid** if a batteries-included grid is preferred | Server-side filtering + **cursor** Prev/Next (not offset), column sort, sticky header. Cursor pagination rules out any grid that assumes offset/total-count. |
| Routing | **React Router v6** | Role-gated route guards from `GET /auth/me`; shareable URLs encode filters (cursor pagination → URLs reflect the current page cursor). |
| Forms / validation | **react-hook-form + zod** | Mirror server validation; map `422 error.fields` onto inputs. |
| i18n | **react-i18next** (`az` default + `ru` + `en`) | `errors.*` keys 1:1 with API `error.code`; ICU plurals; 24-h time, `₼`, money from `amount_minor`. |
| Charts (later) | **Recharts** | For Reports/Dashboard when those batches deploy (⛔ now). |

**Token storage (security):** keep the admin access token **in memory** (SPA variable) — or in an **httpOnly+Secure+SameSite=Strict cookie via a same-origin BFF** if one exists. **Never `localStorage`/`sessionStorage`** (admin is high-privilege, XSS-readable). TTL is 30 min and there is **no admin refresh token**, so in-memory (lost on reload → re-login) is the safe default; the `401` interceptor sends the user back to login.

### 3.2 Architecture & cross-cutting (admin)

```
src/
  api/            axiosClient.ts, queries/ (devices, orders, refunds, subscriptions), types.ts (enums as unions)
  auth/           session (in-memory token), useMe(), RoleGate, login + 2fa flow
  routes/         router.tsx, guards (role-gated)
  features/
    devices/      list, detail (tabs: overview/users/commands/diagnostics/whitelist), create, edit, modals (disable/enable/transfer/decommission/resync)
    orders/       list, detail (timeline), refund modal, recheck modal
    refunds/      list
    subscriptions/ list
    dashboard|users|admins|lookups|reports|audit|settings|flags|templates/  ⛔ stubbed, hidden by RoleGate + feature flag
  components/     DataTable (cursor), FilterBar, StatusPill, MoneyText, ConfirmModal (re-typed phrase), RequestIdToast
  i18n/
```

- **Role model:** the deployed `AdminUser.role` enum is **exactly `super_admin` and `technical`** — `support`/`finance` are **not** deployed; do **not** invent role strings. Gate nav/actions by `role` from `GET /auth/me` for UX only; the server enforces (`403`). Super-admin-only: disable, enable, transfer, decommission, refund, recheck. `technical`+super: create, update device, resync whitelist.
- **No admin refresh token:** plan re-login UX on `401`/expiry. Lockout after 5 failed logins (15 min) — surface "try again in N minutes," don't hammer the endpoint.
- **Idempotency-Key:** set on all mutating calls; **required** on refund. On `409 idempotency_mismatch`, use a fresh key.
- **Error handling:** standard envelope; branch on `error.code`; always surface `request_id` in error toasts (cross-link to Audit when that ships). `422` → `error.fields` onto the form. Honour `Retry-After` on `429`.
- **States:** skeleton table rows (loading), plain message line (empty), inline error + retry, confirm-with-re-typed-value for destructive actions, offline banner + disabled mutations.

### 3.3 Screen-by-screen → endpoints → status

#### Authentication (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| A-01 Login | Email + password (phase 1) | `adminLogin` (returns `AdminAuthSuccess` **or** a `challenge_token`) | ✅ |
| A-02 2FA verify | TOTP **or** recovery code (phase 2) | `adminVerify2fa` | ✅ |
| A-99* Recovery codes (in profile) | Regenerate 8 single-use codes | `regenerateRecoveryCodes` (requires fresh TOTP) | ✅ |
| — Session bootstrap | Hydrate + role-gate nav | `adminMe` (`GET /auth/me`) | ✅ |
| — Logout | Revoke session | `adminLogout` | ✅ |

Branch login on presence of `challenge_token`. On `used_recovery_code == true`, show a banner prompting regeneration. A-03 Forgot password is P2 (no endpoint) — ⛔.

#### Devices — CRUD & lifecycle (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| A-30 Devices list | Search/filter the fleet | `adminListDevices` (`status`, `owner_user_id`, `region_id`, `q`) | ✅ |
| A-31 Device create | Register a device (web alt. to tech mode) | `adminCreateDevice` (`DeviceTechRegister`) | ✅ 🟡 |
| A-32 Device detail | Full operator view (tabs) | `adminGetDevice` (with roster + stats), `adminDeviceCommands`, `adminDeviceDiagnostics`, `adminWhitelistQueue` | ✅ |
| A-33 Device edit | Edit mutable fields | `adminUpdateDevice` (`DeviceAdminUpdate`) | ✅ 🟡 |
| A-34 Resync whitelist modal | Force full whitelist re-push | `adminResyncWhitelist` (202; then poll whitelist-queue) | ✅ |
| A-35 Disable / enable modal | Block/unblock opens | `adminDisableDevice` (super), `adminEnableDevice` (super) | ✅ |
| A-36 Transfer ownership | Reassign owner by phone | `adminTransferDevice` (super) | ✅ |
| — Decommission | Soft-delete with reason | `adminDecommissionDevice` (super; `{reason}`) | ✅ |

**🟡 on create/edit:** the device forms need option lists for `device_model_id`, `sim_operator_id`, `region_id`. The **Lookups** endpoints (`adminListDeviceModels` / `adminListSimOperators` / `adminListRegions`) are **⛔ not deployed**. Until they ship, source these option lists from the backend out-of-band or hard-code per environment. The rest of create/edit/detail is fully buildable.

#### Device telemetry (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| A-32 · Commands tab | Open-command history (all users) | `adminDeviceCommands` (`state`/`since`/`until`) | ✅ |
| A-32 · Diagnostics tab | Health pings (signal/battery/firmware) | `adminDeviceDiagnostics` | ✅ |
| A-32 · Whitelist tab | Pending/recent whitelist changes | `adminWhitelistQueue` (`status`) | ✅ |

#### Orders, refunds, subscriptions (✅)

| Screen | Purpose | operationId(s) | Status |
|---|---|---|---|
| A-40 Orders list | Search financial activity | `adminListOrders` (`status`, `purpose`, `payer_user_id`, `since`, `until`) | ✅ 🟡 |
| A-41 Order detail | Payments + refunds + timeline | `adminGetOrder` (`OrderDetail`) | ✅ |
| A-42 Refund modal | Initiate refund (partial allowed) | `adminRefundOrder` (super; **Idempotency-Key required**) | ✅ |
| A-43 Recheck modal | Re-query Kapital | `adminRecheckOrder` (super) | ✅ |
| A-45 Refunds list | Cross-order refund queue | `adminListRefunds` (`status`) | ✅ |
| A-50 Subscriptions list | Find subs (e.g. expiring) | `adminListSubscriptions` (`status`, `expires_within_days`) | ✅ |
| A-51 Subscription detail | Single sub + periods | `getSubscription` (admin detail route **not deployed**) | ⛔ |

**Notes:** refund `amount_minor` ≤ net (gross − prior refunds); reason 3–255; handle `409` not-refundable and `503` provider-unavailable. The **export-to-report-job** affordance on A-40 is ⛔ (Reports/report-jobs not deployed) — hence 🟡. **A-51:** there is **no deployed admin subscription-detail endpoint**; use the list (`adminListSubscriptions`) for admin and link cross-references; a true per-sub detail screen is ⛔ until/unless that route deploys.

#### Blocked-on-backend admin modules (⛔)

All of the following are designed but **must not** be built against production — they need a planned batch. Hide via RoleGate + feature flag.

| Module / screens | Needs (planned operationIds) | Status |
|---|---|---|
| A-10 Dashboard | `adminMetricsOverview` (`/metrics/overview`) | ⛔ |
| A-20/21/22 Users (customers) | `adminListUsers`, `adminGetUser`, `adminBlockUser`, `adminUnblockUser` | ⛔ |
| A-25/26 Admins | `adminListAdmins`, `adminCreateAdmin`, `adminUpdateAdmin`, `adminOffboardAdmin` | ⛔ |
| A-60..64 Reports + Report Jobs | `adminReportsRevenue/Devices/Subscriptions`, `adminListReportJobs`, `adminGetReportJob` | ⛔ |
| A-70/71 Audit log | `adminAuditSearch` | ⛔ |
| A-80 Settings | `adminListSettings`, `adminUpdateSetting` | ⛔ |
| A-81 Feature flags | `adminListFeatureFlags`, `adminUpdateFeatureFlag` | ⛔ |
| A-85/86/87 Notification templates | `adminListNotificationTemplates`, `adminUpdate…`, `adminPreview…` | ⛔ |
| A-90/91/92/93 Lookups | `adminListSimOperators/DeviceModels/Regions` | ⛔ (also unblocks the 🟡 device forms above) |

---

## 4. Phased Delivery Plan

Sequencing follows the constitution's order (R-WF-01): backend phases gate frontend work. Each phase below is shippable; later phases unlock as backend batches land.

### Phase 1 — Auth + the Open-Gate MVP  *(highest priority; the product)*
**Mobile:** core network layer (dio + AuthInterceptor single-flight refresh + ApiException), secure storage, go_router guards, l10n scaffold (az/ru/en). Screens: S-01, S-04, S-05, S-08, S-10, S-11, **S-12 open flow (polling-based, biometric-gated, cooldown countdown, driver-aware success/fail + feedback)**, S-13, S-14. System screens S-90/S-93.
**Admin:** axios client + 401 re-login + role-gate; A-01 + A-02 (login + 2FA), session bootstrap (`adminMe`), logout.
**Backend dependency:** all deployed today. **Hardware gate (T1):** real opens are not trustworthy until Traccar→`OUTPUT0`/`cmdout.p` is proven on a real **UMKa 310 v2L** device. The open-flow UI can be built and tested against staging/mock commands, but **launch of real opens waits on the T1 hardware proof** (see §6).
**Exit:** a user can OTP-log-in, see devices, and drive the full open lifecycle with correct cooldown/feedback states; the 1 s polling fallback works end-to-end (R-ARCH-12 launch criterion).

### Phase 2 — Subscriptions & Payments
**Mobile:** S-30, S-31 (renew + auto-renew toggle with graceful `409`), S-32 checkout, S-33 Kapital WebView, S-34 result (getOrder/recheck), optional orders list. Wire Idempotency-Key persistence for `createOrder`/`renewSubscription`.
**Admin:** A-40 orders list, A-41 order detail, A-42 refund (Idempotency-Key required), A-43 recheck, A-45 refunds list, A-50 subscriptions list.
**Backend dependency:** all deployed. **External dependency:** Kapital **sandbox** until production credentials + 3DS return URL are live; auto-renew stays disabled (no tokenization).
**Exit:** a user can pay/renew through Kapital and see authoritative status; an admin can refund/recheck and audit-trail it.

### Phase 3 — Admin Device Ops (fleet management)
**Admin:** A-30 list, A-31 create (🟡 lookups), A-32 detail with all tabs (commands/diagnostics/whitelist), A-33 edit, A-34 resync, A-35 disable/enable, A-36 transfer, decommission.
**Mobile:** Technical mode T-01→T-03→T-05→T-06 (T-04 diagnostics ping deferred).
**Backend dependency:** all deployed except Lookups (device-form option lists) — work around per §3.3.
**Exit:** ops can provision, configure, disable/transfer/decommission devices and inspect telemetry; installers can register + assign + test-open from mobile.

### Phase 4 — Features unlocked as backend batches land
Switch on the feature-flagged stubs as each batch deploys. Rough dependency map:

| When this backend batch deploys… | …unlock these frontend screens |
|---|---|
| **Profile** (`GET/PATCH/DELETE /me`, consents) | S-06, S-07, S-50, S-51, S-52, S-54, S-55, S-56 |
| **Roster / Invitations** | S-15, S-16, S-17, S-18 + deep link `salam://invite/{token}` + S-11 Users tab |
| **Notifications** (+ push-token register) | S-40, S-41, S-42; activate FCM registration; payment-result + renewal-reminder pushes |
| **Lookups** (regions/models/sim-operators) | unblocks 🟡 admin device create/edit option lists; A-90/91/92/93 |
| **Dashboard** (`metrics/overview`) | A-10 |
| **Users (customers)** | A-20, A-21, A-22 |
| **Admins** | A-25, A-26 |
| **Reports + Report Jobs** | A-60..A-64 + Orders export action |
| **Audit** | A-70, A-71 + `request_id` cross-links from every error toast |
| **Settings / Feature Flags** | A-80, A-81 + a real source for S-91/S-92 mobile flags |
| **Notification Templates** | A-85, A-86, A-87 |
| **Technical diagnostics ping** | T-04 |
| **Reverb / WebSocket** (post-MVP optimisation) | swap S-12 from polling-only to WS-with-polling-fallback |

---

## 5. Testing Strategy

### Mobile (Flutter)
- **Unit:** repositories (DTO↔domain mapping), `ApiException.from` (every error `code`), the **open-flow `StateNotifier` transition table** (queued→…→opened/dispatched/failed/expired/timedOut, cooldown 429, driver-confirms vs not), token-refresh single-flight, cursor pagination.
- **Widget:** each screen's **six states** (idle/loading/empty/error/success/offline) with overridden Riverpod providers; the Open button's biometric gate + cooldown countdown; checkout/result rendering from order status; localized error rendering for az/ru/en.
- **Integration (`integration_test`):** OTP → home → **open a (mock/staging) gate** golden path including the poll loop; renew → Kapital sandbox WebView → result via getOrder/recheck. Mock the HTTP layer (dio adapter) for deterministic runs; one suite against staging.
- **Golden tests** for the open-flow visual states (green/check vs paper-plane vs red).

### Admin (web)
- **Component (Vitest + Testing Library):** DataTable cursor Prev/Next, FilterBar query composition, ConfirmModal re-typed-phrase guard, role-gated rendering (`super_admin` vs `technical`), 422→field mapping, 401→redirect.
- **E2E (Playwright):** two-phase login + 2FA (TOTP and recovery-code paths, lockout message); device disable/transfer/decommission happy + 403 paths; **refund with Idempotency-Key** including `409 idempotency_mismatch` recovery and `503` provider-unavailable; cursor pagination across a multi-page list.
- **Contract:** generate TS types from `openapi/v1.yaml` and assert the admin enums/union types compile against fixtures so a backend contract change breaks the build, not production.

### Shared
- i18n CI parity (az/ru/en key + placeholder + plural coverage; `errors.*` ↔ API `error.code` 1:1).
- Accessibility checks (WCAG AA: contrast, tap targets, keyboard nav for admin, reduced-motion).

---

## 6. Release & Rollout

- **The HB1 / T1 hardware gate before real opens.** Real remote opens are **not** trustworthy until the empirical T1 gate is green: prove Traccar's Wialon `custom` framing executes `OUTPUT0=1/0` (`cmdout.p`) on a real **GLONASSSoft UMKa 310 v2L** and that actuation read-back confirms the relay moved. Until then, the open-flow ships against staging/mock commands only; **do not enable production opens** for end users. This gate also validates `driver_confirms_actuation` semantics (Traccar output read-back) that S-12's success copy depends on.
- **Mobile store rollout.** iOS App Store + Google Play. **Staged rollout** (Play: 5%→20%→50%→100%; iOS phased release). Ship S-92 "Update required" from day one so we can force-upgrade if a contract change (e.g. the planned OpenAPI v1.2 that removes `message`) requires it — R-API-09 makes that a coordinated backend+mobile+admin release. Wire FCM in the first build (dormant) so enabling push later needs no resubmission.
- **Admin rollout.** Internal web app behind the same-origin host; deploy behind a VPN/allowlist; super-admin accounts seeded with 2FA enforced; recovery codes generated and stored offline before go-live.
- **Feature-flag discipline.** Every ⛔ screen ships dark behind a flag; flipping a flag must require the corresponding backend batch to be live (verified via `route:list`), not just the OpenAPI contract.
- **Launch acceptance (frontend-relevant):** 1 s `getCommand` polling fallback proven end-to-end (R-ARCH-12); biometric-before-open enforced (R-SEC-04); open permission never gated client-side only (R-DOM-05).

---

## 7. Risks & Assumptions

| # | Risk / Assumption | Impact | Mitigation |
|---|---|---|---|
| R1 | **Reverb/WebSocket deferred → polling.** | Slightly higher latency-to-feedback; more request volume on `getCommand`. | Build S-12 polling-first (the launch criterion). WS is a Phase-4 optimisation; the state machine already supports swapping the event source. |
| R2 | **T1 hardware gate not yet proven** (Traccar→`OUTPUT0` on a real UMKa 310). | Real opens can't launch even though the UI is done. | Develop/test against mock + staging commands; treat the T1 proof as the production-open launch gate; keep `driver_confirms_actuation` driving success copy so the UI is correct whichever way the read-back resolves. |
| R3 | **Kapital is sandbox** until prod creds + tokenization land. | No auto-renew; payment success copy unverified against prod 3DS. | Render auto-renew toggle but handle `409` gracefully; rely on `getOrder`/`recheckOrder` to confirm; gate prod payments on credential availability. |
| R4 | **SMS OTP provider in sandbox / TBD.** | OTP delivery reliability/cost unknown pre-launch. | Build robust resend + `Retry-After` countdown + "no signal?" help; don't assume instant delivery; confirm provider before staged rollout. |
| R5 | **Notifications not deployed** → no push. | Payment-result "we'll notify you" and renewal reminders can't fire; users must self-check. | Make `getOrder`/`recheckOrder` and the Subscriptions screen the recovery paths; FCM wired but dormant; unblock in Phase 4. |
| R6 | **Lookups not deployed** → admin device create/edit lack option lists. | 🟡 device forms incomplete. | Source model/operator/region lists out-of-band or hard-code per environment until Lookups ships; the rest of device ops is unaffected. |
| R7 | **No admin refresh token** (30-min TTL). | Admins re-login on expiry/reload; in-memory token lost on reload. | Plan re-login UX; 401 interceptor redirects to login; never use `localStorage` for the admin token. |
| R8 | **`support`/`finance` admin roles are NOT deployed** (only `super_admin`, `technical`). | Inventing role strings would break gating. | Gate strictly on the two deployed roles; treat refund as `super_admin`; confirm with backend before adding any role. |
| R9 | **Error-contract drift (OpenAPI v1.2 removes localized `message`).** | If we branch on `message`, a contract change breaks us. | Branch only on `error.code` / `message_key`; show `message` as a fallback string only; keep az/ru/en bundles authoritative. |
| R10 | **No admin subscription-detail endpoint.** | A-51 can't be a true detail screen. | Use `adminListSubscriptions` + cross-links for MVP; defer A-51 until/unless the route deploys. |
| R11 | **`orderId` return-URL hint is unverified.** | Rendering another user's order detail from a spoofed return. | S-34 independently verifies the caller is the payer before rendering; fall back to most-recent in-flight order, else "indeterminate." |
| R12 | **Real device-fleet validation pending** (only one confirmed model: UMKa 310 v2L). | Driver/firmware assumptions may shift. | Keep `driver_type` (`traccar`/`ble`/`sms`) and `driver_confirms_actuation` data-driven from the API; don't hard-code per-model behaviour in the UI. |

---

## 8. Summary

- **2 surfaces:** Flutter mobile (Riverpod + dio + go_router + webview_flutter + local_auth) and a React + TypeScript admin (react-query + axios + cursor data-grid).
- **The product is the open-gate flow (S-12):** biometric-gated, `openDevice`→poll `getCommand`, cooldown countdown from `429 Retry-After`, driver-aware success/fail + feedback, polling because Reverb is deferred.
- **Everything buildable is tied to a deployed endpoint;** everything else is feature-flagged dark and unlocked per backend batch in Phase 4.
- **4 phases**, gated by backend batches and the **T1 hardware proof** before real opens launch.

*Cross-references: API shapes in [`FLUTTER_API_INTEGRATION.md`](FLUTTER_API_INTEGRATION.md) and [`ADMIN_API_INTEGRATION.md`](ADMIN_API_INTEGRATION.md); deployed surface in [`_api_ground_truth.md`](_api_ground_truth.md); screen behaviour in [`UI_UX_SPECIFICATION.md`](UI_UX_SPECIFICATION.md); product rules in [`PROJECT_CONSTITUTION.md`](PROJECT_CONSTITUTION.md).*
