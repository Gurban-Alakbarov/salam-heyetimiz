# FLUTTER ARCHITECTURE

> Planning only — **no code, no project, no widgets, no packages installed.** This is the foundational document; the other nine reference its conventions. The backend is **frozen** — the app consumes only the already-implemented, prod-verified mobile surface.

---

## 1. Backend mobile surface (what the app may use)

Source of truth: the implemented `/v1` routes (see `docs/reviews/API_REVIEW.md §1`). Base URL `https://api.salamheyetimiz.com`. **The app uses Email-OTP — not phone-OTP (legacy/frozen).**

| Domain | Endpoints the app uses | Auth | Response shape |
|---|---|---|---|
| App config (guest) | `GET /v1/bootstrap` | none | unified envelope |
| Registration | `POST /v1/auth/register`, `/verify-email`, `/resend-otp` | none | unified envelope |
| Login (email-OTP) | `POST /v1/auth/login` → `/verify-email` | none | unified envelope |
| Session | `POST /v1/auth/refresh`, `/auth/logout` | refresh / bearer | legacy bare / 204 |
| Current user (authed bootstrap) | `GET /v1/me` | bearer | unified envelope |
| Biometric unlock flag | `POST /v1/me/biometrics/enroll`, `DELETE /v1/me/biometrics` | bearer | 204 |
| Devices | `GET /v1/devices`, `/devices/{id}`, `/devices/{id}/stats` | bearer | list `{data,page}` / resource |
| **Barrier open** | `POST /v1/devices/{id}/open` → `GET /v1/commands/{id}` → `POST /v1/commands/{id}/feedback`, `GET /v1/devices/{id}/commands` | bearer | domain-specific |
| Orders / checkout | `GET/POST /v1/orders`, `/orders/{id}`, `/orders/{id}/recheck`, `GET /v1/payments/return` | bearer | resource |
| Subscriptions | `GET /v1/subscriptions`, `/subscriptions/{id}`, `POST /renew`, `PATCH /auto-renew` | bearer | list / resource |
| Health | `GET /v1/health/live`, `/health/ready` | none | plain |

**NOT available to mobile (do not build against):** notifications, residents, complexes, refunds (all admin-only or unimplemented). `unread_notifications_count` in `/v1/me` is a reserved `0`. Push-token registration endpoint is **not implemented yet** (plan the client, gate it behind a feature flag). `has_password` is reserved for the future password-login feature.

**Key contract facts:**
- JWT RS256, access TTL 15 min, refresh opaque 60 days (rotating, reuse-detection → on 401-after-refresh-fail the family is revoked).
- Email is the verified identity; phone is collected but unverified.
- Registration: `first_name, last_name, phone, email` → email OTP → `verify-email {email, code, device}` → tokens (auto-login).
- Login: `login {email}` → email OTP → `verify-email` (same endpoint, `wasRegistration=false`).
- Payments: `POST /orders` returns a BirPay **hosted-checkout `confirmUrl`**; the app opens it (webview/browser), the user pays, returns via `/payments/return`, then the app calls `recheck`.
- Bootstrap gates: `app.maintenance_mode`, `app.min_version`, `app.latest_version`, `app.force_update` — the app must honor these on launch.

---

## 2. Architectural style — Feature-First + light Clean Architecture + MVVM

**Decision:** **Feature-first folders**, each feature split into `presentation / domain / data`, with **MVVM** realised through Riverpod Notifiers as ViewModels. Clean Architecture layering is applied **pragmatically** — a UseCase layer only where it earns its keep (multi-step flows like barrier-open, registration, checkout); simple reads go View → Repository directly.

```
Presentation (Screen + Widgets + Riverpod Notifier/ViewModel)
        │  watches state, calls intents
        ▼
Domain (Entity, Repository interface, UseCase[selective])
        │  pure Dart, no Flutter/Dio
        ▼
Data (DTO ←json, Mapper DTO→Entity, RepositoryImpl → ApiClient/Storage)
```

- **Entity** — pure domain object the UI binds to (immutable, `freezed`).
- **DTO** — wire model mirroring the JSON (incl. the unified envelope); never leaks past the data layer.
- **Mapper** — DTO ↔ Entity (keeps the UI insulated from API shape changes, e.g. the dual envelope).
- **Repository (interface in domain, impl in data)** — the single seam between features and the network/storage; returns Entities or a typed `Result`/`Either`.
- **UseCase (selective)** — e.g. `RegisterUseCase`, `OpenBarrierUseCase`, `RefreshSessionUseCase`.

**Why not pure Clean (full UseCase per call):** this is a CRUD+auth+a-few-flows app; a UseCase for every getter is ceremony. Repository + selective UseCases gives testability without boilerplate.

---

## 3. Folder structure

```
lib/
  main_dev.dart  main_qa.dart  main_prod.dart   # flavor entrypoints (--dart-define)
  app.dart                                       # MaterialApp.router + theme + l10n
  bootstrap.dart                                 # init: DI, storage, error zone, bootstrap fetch

  core/
    config/        # AppConfig, Env (flavor), endpoints, constants
    di/            # ProviderScope overrides, global providers
    error/         # AppException, Failure, error mappers, Result type
    network/       # Dio factory, interceptors (auth/refresh/retry/logging/error), ApiClient
    storage/       # SecureStore, KvStore (prefs), CacheStore (Hive) wrappers
    analytics/     # AnalyticsService abstraction + adapters + nav observer  → ANALYTICS_PLAN.md
    crash/         # CrashReporter abstraction + adapters + global handlers  → CRASH_REPORTING.md
    feature_flags/ # FeatureFlagService + composed sources (local/bootstrap/remote)  → FEATURE_FLAGS.md
    connectivity/  # connectivity stream + online/offline state
    permissions/   # runtime permission requests (notifications, biometric, …)
    logger/        # leveled, redacted logger (dev/qa only)
    services/      # app-lifecycle, deep-link, sync, push(flagged) services
    theme/         # ThemeData bootstrap, derived from design_system tokens
    l10n/          # generated localizations bootstrap

  design_system/   # full token + component system  → DESIGN_SYSTEM.md
    tokens/        # colors, typography, spacing, radius, elevation, shadows, durations, icons
    theme/         # light/dark ThemeData + component themes (from tokens)
    components/    # AppButton/Card/Dialog/BottomSheet/TextField/OtpField/SearchField,
                   # AppLoading/Skeleton, Empty/Error/Success states, SnackBar/Toast,
                   # AppScaffold/TopBar/BottomNav/TabBar, StatusBadge, ConnectivityBanner, …
    gallery/       # debug component gallery (storybook) for visual QA + goldens

  features/
    bootstrap/     # guest + /me bootstrap, maintenance/force-update gate
    auth/          # register, verify-email, resend, email-login, session, logout
    home/          # dashboard
    devices/       # GRANULAR: device_list/, device_detail/, device_stats/,
                   # device_history/, device_commands/  (own repo+provider+VM each — FLUTTER_MODULES.md)
    barrier/       # device_open/ : open flow + command polling + feedback
    orders/        # list, detail, checkout (BirPay webview), recheck
    subscriptions/ # list, detail, renew, auto-renew
    profile/       # profile view/edit
    security/      # biometric, (future) set/change password
    notifications/ # in-app inbox shell (future; FCM handler)
    support/       # support contacts, about
    settings/      # theme, language, app prefs
      <feature>/
        data/        { dto/, mapper/, repository_impl.dart, datasource/ }
        domain/      { entity/, repository.dart, usecase/ }
        presentation/{ screens/, widgets/, providers/ (notifiers) }

  routing/         # AppRouter (go_router), routes, guards (auth, gate)
  shared/
    extensions/    # BuildContext, String, DateTime, num, Widget extensions
    validators/    # phone/email/OTP/form validators (mirror backend rules)
    formatters/    # money (AZN minor-unit), date/time (UTC→tz), masking
    constants/     # app-wide constants, regexes, keys
    helpers/       # small pure helpers
    mixins/        # reusable mixins (throttle, debounce, lifecycle)
    widgets/       # cross-feature composite widgets (composed from design_system)

  l10n/            # app_az.arb (template), app_en.arb, app_ru.arb
  assets/          # images, icons, fonts, lottie
```

Tests mirror `lib/` under `test/` + `integration_test/`.

---

## 4. Design System

A modular, token-driven design system under `design_system/` (tokens → theme → components → gallery). **Every screen composes only these tokens + components — no ad-hoc styling.** The full token list, the complete component catalog (AppButton/Card/Dialog/BottomSheet/TextField/OtpField/SearchField, loading/skeleton, empty/error/success states, snackbar/toast, app-bar + navigation components, StatusBadge, …), the dark-mode rule, and the component-gallery/golden approach live in **`DESIGN_SYSTEM.md`**.

Key principle (unchanged): every data screen renders one of `loading (skeleton) / data / empty / error (retry)` via the shared state components — no screen invents its own.

---

## 5. Localization (overview)

- **Languages:** Azerbaijani (`az`, default + template), English (`en`), Russian (`ru`).
- **Mechanism:** `flutter_localizations` + `intl` + ARB files (`l10n/app_az.arb` as the source of truth), generated `AppLocalizations`.
- **Locale source:** user override in Settings → persisted (prefs) → falls back to device locale → falls back to `az`. The chosen locale is sent as `Accept-Language` on API calls (the backend honors it for OTP email copy).
- **Rules:** zero hardcoded user-facing strings; money via `intl` `NumberFormat` (AZN, minor-unit aware); dates via `intl` (device-tz display, UTC from API); RTL not required (az/ru/en all LTR) but the app uses directionality-safe widgets.

---

## 6. Cross-cutting conventions

- **Immutability:** all entities/DTOs/state via `freezed`; no mutable models.
- **Error type:** repositories return `Result<T>` (sealed `Success`/`Failure`) — no exceptions across layer boundaries; the UI maps `Failure` → an `ErrorState`/snackbar (see `API_INTEGRATION.md §error map`).
- **Time/money:** API gives UTC + minor units; the app converts at the presentation edge only.
- **Feature flags:** a `FeatureFlagService` composes local defaults + the **already-live backend** `bootstrap.feature_flags`/`app.*` + a future Remote-Config adapter; every new module ships behind a flag, default off (**`FEATURE_FLAGS.md`**).
- **Observability:** provider-agnostic `AnalyticsService` (**`ANALYTICS_PLAN.md`**) + `CrashReporter` (**`CRASH_REPORTING.md`**) abstractions are wired from day one (Noop by default; Firebase/Sentry/PostHog plug in as adapters) — no PII, no vendor lock-in.
- **No backend changes:** if the app needs something the API lacks (push-token registration, notifications), it is **planned + flagged off**, never worked around with a backend edit.

---

## 7. Document map

| Concern | Document |
|---|---|
| Modules | `FLUTTER_MODULES.md` |
| Screens | `SCREEN_FLOW.md` |
| Navigation / routing / guards | `NAVIGATION.md` |
| API client, interceptors, refresh, errors, push | `API_INTEGRATION.md` |
| State management (Riverpod), repos, DI | `STATE_MANAGEMENT.md` |
| Offline + local storage + cache/sync | `OFFLINE_STRATEGY.md` |
| Security (tokens, pinning, biometric, hardening) | `SECURITY_PLAN.md` |
| Packages + rationale + alternatives | `PACKAGE_SELECTION.md` |
| Testing + flavors + CI + release | `RELEASE_PLAN.md` |
| **Design system (tokens + components + gallery)** | **`DESIGN_SYSTEM.md`** |
| **Feature flags (local + bootstrap + remote)** | **`FEATURE_FLAGS.md`** |
| **Analytics (abstraction + events + adapters)** | **`ANALYTICS_PLAN.md`** |
| **Crash reporting (abstraction + adapters)** | **`CRASH_REPORTING.md`** |
