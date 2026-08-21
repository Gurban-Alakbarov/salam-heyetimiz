# PACKAGE SELECTION

> Planning only — **nothing installed.** Each package: purpose · why · alternative(s). Prefer maintained, popular, null-safe, widely-adopted packages. Pin versions at implementation time.

---

## Core architecture / state / DI

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `flutter_riverpod` + `riverpod_annotation` + `riverpod_generator` | State management + DI | Compile-safe, testable, async-native, no separate DI container (see `STATE_MANAGEMENT.md`) | `flutter_bloc` (more boilerplate), `provider` (less safe), `get_it`+`injectable` (DI only) |
| `freezed` + `freezed_annotation` | Immutable models, unions/sealed (Failure, state) | Standard for immutable entities/DTOs + exhaustive `when` | manual `==`/`copyWith` (error-prone), `built_value` (heavier) |
| `json_serializable` + `json_annotation` | DTO (de)serialization | Codegen, pairs with freezed; handles the unified envelope DTOs | manual `fromJson` (tedious), `dart_mappable` (newer) |

## Networking

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `dio` | HTTP client | Interceptors (auth/refresh/retry/error), cancel tokens, multipart, timeouts (see `API_INTEGRATION.md`) | `http` (no interceptors), `chopper`/`retrofit` (codegen on top of dio — optional later) |
| `dio_smart_retry` *or* custom RetryInterceptor | GET retry/backoff | Idempotent retry on network/5xx | hand-rolled |
| `connectivity_plus` | Online/offline detection | Stream of connectivity for offline gating + sync triggers | `internet_connection_checker` (adds real-reachability check — optional companion) |
| `pretty_dio_logger` | Redacted dev logging | Readable request/response logs (dev/qa only) | custom LoggingInterceptor |

## Routing

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `go_router` | Declarative routing, guards, deep links, ShellRoute bottom nav | Official, URL-based, Riverpod-friendly redirect (see `NAVIGATION.md`) | `auto_route` (codegen, powerful nested), raw Navigator 2.0 (boilerplate) |

## Storage

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `flutter_secure_storage` | Tokens, biometric flag, install_uuid | Keychain/Keystore-backed encryption | `hive` + manual encryption (weaker for secrets) |
| `hive` + `hive_flutter` (+ `hive_generator`) | Structured offline cache + outbox | Fast, typed, no native dep; great for cache (see `OFFLINE_STRATEGY.md`) | `isar` (powerful but heavier), `drift`/sqflite (SQL — overkill for cache), `objectbox` |
| `shared_preferences` | Theme, locale, flags | Simple KV for small non-sensitive prefs | `hive` (could unify, but prefs is simpler for flags) |

## UI / design system

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `cached_network_image` | Image cache (avatars/logos) | Disk+memory cache, placeholders | `extended_image` |
| `shimmer` *or* `skeletonizer` | Skeleton loading states | Consistent loading UX | hand-rolled |
| `flutter_svg` | Vector icons/illustrations | Crisp scalable assets | raster PNGs |
| `lottie` (optional) | Splash/empty animations | Lightweight delight | Rive |
| `webview_flutter` | BirPay hosted checkout | In-app checkout + URL watching for `payments/return` | `flutter_inappwebview` (more features), `url_launcher` (external browser fallback) |
| `url_launcher` | Store link (force-update), support tel/mailto, external fallback | Standard | — |

## Localization

| Package | Purpose | Why |
|---|---|---|
| `flutter_localizations` (SDK) + `intl` | l10n + date/number/money formatting | Official; ARB + generated `AppLocalizations` (az/en/ru) |

## Auth / device / platform

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `local_auth` | Biometric app-lock + barrier gate | Face/Touch ID/biometric (see `SECURITY_PLAN.md`) | `biometric_storage` (combines storage) |
| `package_info_plus` | App version/build (force-update compare, About) | Standard | — |
| `device_info_plus` | Platform/OS/model for the device fingerprint | Builds the `device{}` payload for verify-email | — |
| `uuid` | install_uuid generation | Stable per-install id | platform id (less portable) |

## Push (Future — flagged off)

| Package | Purpose | Why | Alternatives |
|---|---|---|---|
| `firebase_core` + `firebase_messaging` | FCM push (token + handlers) | **FCM chosen** — native, free, Flutter-first, `user_devices.push_token` is FCM-shaped; build now, register when backend ships | OneSignal (extra vendor + SDK), pure APNs/raw (no Android) |
| `flutter_local_notifications` | Display notifications + channels | Foreground/background display + deep-link taps | — |

## Security (P2 / optional)

| Package | Purpose | Phase |
|---|---|---|
| (Dio pinning via `badCertificateCallback`) or `http_certificate_pinning` | SSL/SPKI pinning | P2 |
| `freerasp` *or* `flutter_jailbreak_detection` | Root/jailbreak soft-detection | P2/optional |

## Observability & feature flags (behind abstractions — Noop default)

> All of these sit **behind an abstraction** (`AnalyticsService`, `CrashReporter`, `FeatureFlagService`). The default build uses Noop/local; a vendor is a one-adapter add. None is required for v1 to ship.

| Package | Purpose | Why / status | Alternatives |
|---|---|---|---|
| `firebase_core` | Firebase init (shared by messaging/analytics/crashlytics/remote-config) | Needed once if ANY Firebase adapter is used | — |
| `firebase_analytics` | AnalyticsService → Firebase adapter | Free, standard; **adapter, flagged** (`ANALYTICS_PLAN.md`) | `posthog_flutter` (product analytics + flags), Mixpanel/Amplitude |
| `firebase_crashlytics` | CrashReporter → Crashlytics adapter | Free, native; on in qa/prod (`CRASH_REPORTING.md`) | `sentry_flutter` (richer, cross-stack) |
| `sentry_flutter` | CrashReporter → Sentry adapter (alt) | If Sentry chosen over Crashlytics | Crashlytics |
| `firebase_remote_config` | FeatureFlagService → Remote source (future) | Adds remote % rollout / kill-switch (`FEATURE_FLAGS.md`); **future, one adapter** | `posthog` flags, custom config endpoint |

## Quality / tooling (dev)

| Package | Purpose |
|---|---|
| `flutter_lints` / `very_good_analysis` | Lint ruleset |
| `build_runner` | Codegen runner (freezed/json/riverpod/hive) |
| `mocktail` | Mocks for unit/widget tests (no codegen) |
| `golden_toolkit` *or* `alchemist` | Golden tests for design-system components |
| `patrol` *or* `integration_test` (SDK) | Integration/E2E tests |

---

## Notes
- **Minimise the dependency surface:** prefer one package per concern; avoid mega-packages (e.g. GetX) that blur boundaries.
- **Codegen stack** (freezed + json + riverpod + hive) all run under one `build_runner` — one generation step.
- **Push + pinning + root-detection** are the only packages tied to deferred phases — keep them isolated behind flags so v1 ships lean.
- Final versions chosen at implementation time against the then-current stable Flutter SDK.
