# Flutter — Phase 1 (Infrastructure) Implementation Record

> Status: **infrastructure scaffold built & verified** (analyze green, tests pass,
> APK debug + release + web all build). Emulator gate: see §6.
> Scope: **infrastructure only — no business logic** (per the request + constitution).

Project: `mobile/` · package `salam_mobile` · org `com.salamheyetimiz`
Flutter 3.44.4 (Dart 3.12.2) · platforms: android, ios, web.

---

## 1. What was built (infrastructure only)

| Area | Files | Notes |
|---|---|---|
| Flavors / config | `core/config/app_config.dart`, `main.dart` (dev), `main_qa.dart`, `main_prod.dart`, `bootstrap.dart` | `AppEnv {dev,qa,prod}`; single `bootstrap()` + guarded zone + global error → CrashReporter |
| Design tokens | `design_system/tokens/tokens.dart` | colors (brand `#6D28D9`), spacing (4-based), radius (8/12/20), durations, typography |
| Theme | `design_system/theme/app_theme.dart` | Material 3 light + dark, derived only from tokens |
| Components | `design_system/components/app_components.dart` | `AppButton`, `AppScaffold`, `AppLoading`, `EmptyState`, `ErrorStateView`, `PlaceholderScreen` |
| Navigation | `routing/app_router.dart` | go_router; routes: `/`, `/welcome`, `/auth/register`, `/auth/login`, `/auth/verify`, `/home`, `/settings` |
| Screens (placeholder) | `features/splash`, `features/auth`, `features/home`, `features/settings` | Splash, Welcome, Register, Verify-OTP, Login, HomeShell (4-tab bottom nav: Home/Devices/Orders/Profile), Settings (live theme + language switch) |
| Localization | `l10n.yaml`, `lib/l10n/app_{en,az,ru}.arb` → generated `app_localizations.dart` | az / en / ru |
| Network | `core/network/api_client.dart` | Dio factory + `AuthInterceptor`, `LoggingInterceptor`, `mapDioError → Failure`; public-path allowlist; base URL from config |
| Storage | `core/storage/app_storage.dart` | `SecureStore` (tokens), `KvStore` (prefs), `CacheStore` (Hive) |
| Error model | `core/error/failure.dart` | sealed `Failure` taxonomy + `Result<T>` |
| Observability | `core/logger/app_logger.dart`, `core/analytics/analytics_service.dart`, `core/crash/crash_reporter.dart` | logger wrapper; analytics + crash abstractions with Noop default |
| Feature flags | `core/feature_flags/feature_flag_service.dart` | enum + `LocalFeatureFlagService` (new modules default OFF) |
| Connectivity | `core/connectivity/connectivity_service.dart` | online stream + check |
| DI (Riverpod) | `core/di/providers.dart` | all services as providers; `appConfigProvider` overridden per flavor; `ThemeModeNotifier`, `LocaleNotifier` |
| App root | `app.dart` | `MaterialApp.router` wired to themes + l10n + router |
| CI | `scripts/ci.ps1`, `scripts/ci.sh` | pub get → gen-l10n → format → analyze → test → build apk/web |
| Assets | `assets/images/`, `assets/icons/` | structure only (declared in pubspec when first asset lands) |
| Test | `test/widget_test.dart` | smoke: boot → splash → navigate |

## 2. Packages (pre-approved only — no new packages)

flutter_riverpod, go_router, dio, flutter_secure_storage, hive, hive_flutter,
shared_preferences, connectivity_plus, intl, package_info_plus, device_info_plus,
uuid, cached_network_image, logger, flutter_localizations (sdk). Dev: flutter_lints.

**Codegen tooling (freezed / json_serializable / riverpod_generator / build_runner)
is intentionally deferred to Phase 2**, when the first DTO/Entity models arrive —
Phase 1 has no models, so adding build_runner now would add a fragile, empty
codegen step. This is consistent with the constitution's "selective codegen".

## 3. NOT written in Phase 1 (per request + constitution)

Authentication, Registration, OTP, Orders, Payments, Barrier, Device logic,
Subscriptions, business API calls, Repository implementations, UseCase
implementations, real screen UI. All screens are placeholders.

## 4. Verification (this session)

| Gate | Result |
|---|---|
| `flutter analyze` | ✅ **No issues found** |
| `dart format` | ✅ applied (28 files) |
| `flutter test` | ✅ **All tests passed** |
| `flutter build apk --debug` | ✅ `app-debug.apk` (~177 MB, debug) |
| `flutter build apk --release` | ✅ `app-release.apk` (48.1 MB, debug-key signed) |
| `flutter build web` | ✅ `build/web` (39 files) |

## 5. Deviations / notes

- Screens grouped pragmatically (e.g. auth placeholders in one file) — structure
  is feature-first; per-screen splitting happens when real UI lands.
- Theme-mode + locale are in-memory (Phase 1). Persistence (KvStore) is Phase 2.
- RefreshInterceptor (single-flight 401 → `/v1/auth/refresh`) is stubbed as a
  comment; real refresh needs the auth feature (Phase 2).

## 6. Emulator gate — waived (test on physical device)

The user opted to **test the debug APK on a physical phone over USB** instead of
the emulator, so the emulator gate is waived for Phase 1.

For reference, the emulator toolchain was set up this session anyway (cmdline-tools,
`system-images;android-34;google_apis;x86_64`, AVD `salam`). Note: Hyper-V / VBS is
**running** on the host (`HypervisorPresent=True`), so the emulator needs **WHPX
(Windows Hypervisor Platform)** for acceleration — currently **Disabled** (enabling
needs admin + reboot). Physical-device testing avoids this entirely.

Debug APK: `mobile/build/app/outputs/flutter-apk/app-debug.apk`.
Going forward, only debug builds are produced routinely (release on request).
