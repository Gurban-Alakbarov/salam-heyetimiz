# FLUTTER PROJECT CONSTITUTION

> **Status: BINDING & IMMUTABLE.** These are the non-negotiable development rules for the Salam Həyətimiz Flutter app. Every task, every commit, every PR, and every AI-assisted change MUST comply. No later task may violate or silently override this constitution; changing it requires the explicit **Amendment** process (§21).
> This document is the *law*; the 14 planning docs in `docs/flutter/` are the *detailed design* that implements it. Where a planning doc adds detail, it must stay consistent with this constitution.

**Keywords:** **MUST / MUST NOT** = hard rule (PR-blocking). **SHOULD** = strong default (deviation needs a written reason in the PR).

---

## 1. Architecture Rules

1.1 **Feature-First** — code is organised by feature (`features/<name>/`), not by technical layer. (`FLUTTER_ARCHITECTURE.md §3`)
1.2 **Light Clean Architecture** — each feature splits into `presentation / domain / data`. Domain is pure Dart (no Flutter, no Dio).
1.3 **MVVM** — a Riverpod `Notifier`/`AsyncNotifier` is the ViewModel; the View only watches state + calls intents.
1.4 **Riverpod** is the single state-management + DI system. No other state/DI framework MUST be introduced.
1.5 **Repository Pattern** — every feature talks to the outside world only through a repository (interface in `domain`, impl in `data`).
1.6 **DTO → Entity → ViewModel flow** — JSON ↔ `DTO` (data layer only) → `Mapper` → `Entity` (domain) → ViewModel state. DTOs MUST NOT leak past the data layer; the UI binds to Entities.
1.7 **Design-System-First** — UI is composed from design-system components + tokens (§4).
1.8 **Offline-First mindset** — reads degrade to cache; state-changing/financial/real-time actions fail fast (§8).
1.9 **API-Contract-First** — the backend OpenAPI is the source of truth; the app conforms to it (§10).
1.10 **UseCases are selective** — added only for multi-step / cross-repository flows (register, barrier-open, checkout); plain reads call the repository directly.

---

## 2. Coding Rules (prohibited)

The following are **MUST NOT** (PR-blocking):
- ❌ HTTP call inside a Widget.
- ❌ Business logic inside a Widget.
- ❌ JSON parse / serialization inside a Widget or ViewModel.
- ❌ `setState`/`StatefulWidget` for app state (local ephemeral UI animation state is the only exception).
- ❌ UI / `BuildContext` / widgets inside a Repository, Service, UseCase, or Entity.
- ❌ Widget references inside a ViewModel.
- ❌ Hardcoded URL / base path (use `core/config/endpoints.dart`).
- ❌ Hardcoded color / size / radius / shadow / duration (use design tokens — §4).
- ❌ Hardcoded user-facing string (use l10n — §4.6).
- ❌ Magic numbers (named constants / tokens only).
- ❌ Direct `Dio` usage outside `core/network` (§6).
- ❌ Throwing/propagating raw exceptions across layers (use `Failure` — §7).
- ❌ Mutable models (use `freezed` immutables).
- ❌ `print` / unredacted logging (use the `logger`; never log PII/tokens/OTP — §16).
- ❌ TODO/FIXME left in merged code (resolve or open a tracked issue).

---

## 3. Dependency Rules

3.1 Dependencies flow **one direction only**:
```
Presentation → Domain → (Repository interface) → Data(RepositoryImpl) → Network/Storage → Backend
```
The reverse MUST NOT happen (Domain MUST NOT import Presentation; Domain MUST NOT import Dio/Flutter).
3.2 **Feature isolation** — a feature MUST NOT import another feature's internal files (`data/`, `presentation/`, private providers). Cross-feature sharing happens only via: `core/`, `shared/`, `design_system/`, a feature's **public domain entity/contract**, or a shared provider.
3.3 No circular dependencies between features or layers.
3.4 `core/` and `design_system/` MUST NOT depend on any `features/`.

---

## 4. Design System Rules

4.1 Screens MUST NOT use raw `Container` (for styling), `ElevatedButton`, `OutlinedButton`, `TextButton`, `TextField`, `Card`, `Dialog`, `BottomSheet`, `SnackBar`, `AppBar`, bottom-nav, etc. directly. They MUST use the design-system components (`AppButton`, `AppTextField`, `AppCard`, `AppDialog`, …) (`DESIGN_SYSTEM.md`).
4.2 All colors, typography, spacing, radius, elevation, shadows, durations, and icons MUST come from **tokens** — never literals.
4.3 Light + **dark** mode are both first-class; every component/token has a dark variant.
4.4 Every data screen MUST render exactly one of `loading (skeleton) / data / empty / error (retry)` via the shared state components.
4.5 New shared UI patterns become a design-system component before reuse (no copy-paste styling).
4.6 **Localization:** zero hardcoded strings; az (default) / en / ru via ARB; money via `intl` (AZN minor-unit); dates UTC→device-tz at the presentation edge only.

---

## 5. State Management Rules

5.1 **No "god provider."** State is split into small, single-responsibility providers (`STATE_MANAGEMENT.md §7`, `FLUTTER_MODULES.md §Devices granular`).
5.2 Provider choice:
- **`AsyncNotifier`** — async screen state (fetch + loading/data/error). *Most screens.*
- **`Notifier`** — synchronous/stateful flows (forms, the barrier-open poll loop, toggles).
- **`FutureProvider`** — a one-shot derived async value with no intents.
- **`Provider`** — pure derived/computed values + DI of services/repositories.
5.3 Screen-scoped providers MUST be **`autoDispose`**; per-resource state MUST use **`family`** (by id). Keep-alive only for infra/session (`authState`, `bootstrap`, `me`).
5.4 **Granular rebuilds** — widgets watch via `select` so they rebuild only on the field they use.
5.5 ViewModels expose **intents** (methods); Views never mutate state directly.
5.6 Cross-cutting services (`analytics`, `crashReporter`, `featureFlag`) are read from UseCases/ViewModels, never from widgets.

---

## 6. Networking Rules

6.1 Every HTTP call goes through the chain **`ApiClient → Repository → ViewModel`**. Direct `Dio` use outside `core/network` is **MUST NOT**.
6.2 The interceptor chain (Auth · single-flight Refresh-on-401 · Retry[idempotent GET only] · Connectivity · Logging[dev/qa] · ErrorMapper) MUST NOT be bypassed or reordered without an amendment (`API_INTEGRATION.md §2`).
6.3 The **refresh flow** (single-flight, rotation, session-wipe on failure) MUST NOT be broken or duplicated. POST/PATCH/DELETE MUST NOT be blind-retried.
6.4 Endpoint paths live in `core/config/endpoints.dart` — no string literals in repositories. `Accept-Language` is sent on every call.

---

## 7. Error Handling Rules

7.1 All errors MUST be mapped to the typed `Failure` model (`API_INTEGRATION.md §4`). The UI MUST NOT see raw `DioException`/`Exception`.
7.2 `SnackBar` / `Dialog` / `BottomSheet` / `ErrorState` MUST render only from the `ErrorMapper`/`Failure` result — never an ad-hoc error string.
7.3 Repositories return `Result<T>` (`Success`/`Failure`); no exceptions cross layer boundaries.
7.4 The HTTP-status→UX mapping (`API_INTEGRATION.md §5`) is the canonical behavior for 401/403/404/409/422/429/5xx/offline/timeout/refresh-expired/maintenance/force-update.

---

## 8. Offline Rules

8.1 The offline strategy (`OFFLINE_STRATEGY.md`) MUST NOT be weakened.
8.2 **Barrier Open and Payment/Checkout MUST NOT work offline** (and MUST NOT be queued) — they require the live, server-authoritative round-trip.
8.3 Read-only screens MUST serve from cache (stale-while-revalidate) with an offline/last-updated indicator.
8.4 Only safe, idempotent, non-financial writes may be queued; the server is authoritative on conflict.

---

## 9. Feature Completeness Rules

9.1 A feature is merged **only when complete**: Repository + ViewModel + Screen(s) + Route + Localization + Tests + Analytics events + Crash handling + a Feature Flag — **all together**.
9.2 **No half-features merged.** A WIP feature lives behind a default-OFF flag (§13) on its branch until complete.

---

## 10. API Rules

10.1 Flutter MUST NEVER change, work around, or assume a backend contract. **The backend is the source of truth.**
10.2 The OpenAPI spec (`docs/openapi/openapi.json`) is the contract reference; DTOs mirror it.
10.3 No fabricated/mock domain models in production code. Mock data is allowed **only** in tests / the local mock server.
10.4 If the app needs something the API lacks, it is **planned + flagged off** — never solved by editing the backend or faking the response.

---

## 11. Analytics Rules

11.1 Analytics flows only through `AnalyticsService` (`ANALYTICS_PLAN.md`). A screen/widget MUST NOT call Firebase/PostHog/any vendor SDK directly.
11.2 Events are the typed catalog; params MUST NOT contain PII (email/phone/OTP/token/card).
11.3 Key flows log events at the UseCase/ViewModel boundary; screen_view is auto-tracked via the nav observer.

---

## 12. Crash Rules

12.1 Crash/error reporting flows only through the `CrashReporter` abstraction (`CRASH_REPORTING.md`). No direct vendor SDK calls from features.
12.2 Global handlers (`FlutterError.onError`, `PlatformDispatcher.onError`, `runZonedGuarded`) are wired once in `bootstrap.dart`. Breadcrumbs/keys MUST NOT contain PII.

---

## 13. Feature Flag Rules

13.1 A new module MUST NOT be merged without a feature flag (`FEATURE_FLAGS.md`).
13.2 Not-yet-ready functionality is **default OFF**.
13.3 Flags resolve via `FeatureFlagService` (local default + live backend bootstrap flags + future remote adapter); `maintenance`/`force-update` from the backend always win.

---

## 14. Performance Rules

14.1 No N+1 rebuilds — granular providers + `select` (§5).
14.2 Pagination is **mandatory** for every list (cursor contract `{data,page}`); no unbounded fetch.
14.3 Lazy loading + infinite scroll for long lists; `ListView.builder`/slivers (virtualised).
14.4 Standard image caching (`cached_network_image`) with sized requests + placeholders.
14.5 `autoDispose` to free state; cancel in-flight requests on dispose; bounded poll loops.
14.6 Every PR assesses its performance impact (§18).

---

## 15. Testing Rules

15.1 Every feature ships with at minimum: **Unit** (repository/mapper/usecase/notifier), **Widget** (screen states), **Golden** (design-system components, where applicable), **Integration** (critical flows: register/verify, login, barrier-open, checkout, refresh, force-update).
15.2 A feature MUST NOT be merged without tests.
15.3 Tests use overridden providers/fakes (no live network); the barrier-open state machine + auth/refresh are the highest-priority test targets (`RELEASE_PLAN.md §3`).

---

## 16. Security Rules

16.1 Tokens (access + refresh) MUST live **only** in secure storage. Never in prefs/Hive/logs.
16.2 JWT, refresh tokens, and OTP codes MUST NEVER be logged.
16.3 PII (email, phone, card, full name) MUST NEVER be logged, sent to analytics, or put in crash breadcrumbs/keys.
16.4 No token in URLs, deep links, or the payment webview.
16.5 Biometric is app-lock + barrier-gate, **not** a login factor (login is email-OTP).
16.6 SSL/certificate pinning is reserved for P2 (needs backend cert/rotation coordination) — planned, not skipped (`SECURITY_PLAN.md`).
16.7 Release builds are obfuscated (`--obfuscate --split-debug-info`); no network logging in prod.

---

## 17. Release Rules

17.1 A feature MUST NOT reach Production before it is complete (§9) and tested (§15).
17.2 Incomplete work develops behind a default-OFF feature flag.
17.3 **Flavors:** `dev / qa / prod` via `--dart-define` + platform flavors; distinct applicationId/bundleId/name/icon per flavor; version = semver (aligned with backend `min_version`), build number set by CI (`RELEASE_PLAN.md §1–2`).
17.4 Staged store rollout; the backend `min_version`/`force_update` lever is the emergency kill-switch.

---

## 18. Code Review Rules

Every PR MUST pass these gates (a reviewer answers each; a "no" blocks merge):
1. Conforms to the architecture (§1) + dependency direction (§3)?
2. Uses the design system + tokens only (§4)?
3. Offline rules intact (§8)?
4. Backend contract unchanged + OpenAPI-aligned (§10)?
5. Analytics events added (§11)?
6. Crash handling present (§12)?
7. Localized (az/en/ru) (§4.6)?
8. Tests written (§15)?
9. Behind a feature flag if new/incomplete (§13)?
10. Performance impact assessed (§14)?
11. No prohibited patterns (§2)? No PII/token logging (§16)?
12. No duplicate code / reuses existing components + providers (cf. `AI_DEVELOPMENT_RULES.md`)?

CI MUST enforce: `flutter analyze` clean, `dart format` clean, all tests green, before merge.

---

## 19. AI Development

AI-assisted development is governed by the companion document **`AI_DEVELOPMENT_RULES.md`**, which is part of this constitution and equally binding.

---

## 20. Precedence

If any later instruction, planning doc, or generated code conflicts with this constitution, **this constitution wins** — unless amended (§21). When a conflict is detected, stop and surface it; do not silently override.

---

## 21. Amendment process

This document is immutable except by an explicit amendment: a written proposal stating the rule changed, the reason, and the impact, approved by the product owner. Amendments are versioned (changelog below). No code change may alter a rule without a recorded amendment.

**Changelog**
- v1.0 (2026-06-29) — initial constitution ratified (pre-implementation).
