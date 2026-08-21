# STATE MANAGEMENT

> Planning only — a decision document, no implementation.

---

## 1. Decision: **Riverpod v2** (`flutter_riverpod` + `riverpod_annotation`/generator)

**Chosen:** Riverpod 2 with the code-generated `Notifier` / `AsyncNotifier` style.

**Why Riverpod over the alternatives:**

| Option | Verdict | Reason |
|---|---|---|
| **Riverpod 2 (Notifier)** | ✅ **Chosen** | Compile-safe (no runtime provider-not-found), no `BuildContext` needed for reads, `AsyncNotifier` models loading/data/error natively (fits our screen-state matrix), built-in DI (providers ARE the container), trivial to override in tests, great for our mix of async reads + a few stateful flows. |
| Bloc / Cubit | Good, not chosen | Excellent for complex event-sourced flows, but heavier boilerplate (events/states/mappers) for what is mostly CRUD + async fetch. Cubit alone ≈ Riverpod Notifier with more ceremony. |
| Notifier-only / ChangeNotifier / Provider | No | Provider lacks compile-safety + family/autoDispose ergonomics; ChangeNotifier is mutable + harder to test. |
| GetX / MobX | No | GetX mixes routing/DI/state with weak boundaries; MobX adds codegen without Riverpod's testability edge. |

**How MVVM maps:** a screen's `AsyncNotifier`/`Notifier` **is** its ViewModel — it exposes immutable state + intent methods, depends on repositories (injected via providers), and the View `ref.watch`es it.

---

## 2. Provider taxonomy

- **Infrastructure providers** (root, app-lived): `dioProvider`, `apiClientProvider`, `secureStoreProvider`, `cacheStoreProvider`, `prefsProvider`, `connectivityProvider`, `routerProvider`, `authStateProvider`, `bootstrapProvider`, `localeProvider`, `themeModeProvider`, `loggerProvider`, **`analyticsProvider`**, **`crashReporterProvider`**, **`featureFlagServiceProvider`** + `featureFlagProvider(flag)` (see `ANALYTICS_PLAN.md` / `CRASH_REPORTING.md` / `FEATURE_FLAGS.md`).
- **Repository providers** (one per feature): `authRepositoryProvider`, `deviceRepositoryProvider`, `orderRepositoryProvider`, `subscriptionRepositoryProvider`, `barrierRepositoryProvider`, `profileRepositoryProvider`.
- **Feature/screen ViewModels** (`autoDispose`, often `family` by id): e.g. `deviceListProvider` (AsyncNotifier, paginated), `deviceDetailProvider(id)`, `barrierOpenProvider(deviceId)` (Notifier with a poll loop), `registerProvider`, `verifyOtpProvider`, `checkoutProvider(orderId)`.
- **autoDispose by default** for screen state (free memory on pop); keep alive only the infra + session.

---

## 3. Repository pattern (yes)

- **Domain** declares the interface (`abstract class DeviceRepository`), returns **Entities** + `Result<T>`.
- **Data** implements it (`DeviceRepositoryImpl`) using `ApiClient` + storage + mappers.
- ViewModels depend on the **interface** (provider returns the impl) → tests inject a fake repository.
- Repositories also own the **read cache** (return cached entity first, then refresh — see `OFFLINE_STRATEGY.md`).

```
deviceDetailProvider(id)  →  ref.read(deviceRepositoryProvider).getDevice(id)  →  Result<Device>
```

---

## 4. UseCases (selective)

A `UseCase` is added only for **multi-step / cross-repository** business flows, not for plain getters:

- `RegisterUseCase` / `VerifyOtpUseCase` — orchestrate register/login → verify → persist tokens → set `authState`.
- `OpenBarrierUseCase` — biometric gate → POST open → poll command → terminal result.
- `RefreshSessionUseCase` — single-flight refresh (also used by the interceptor).
- `CheckoutUseCase` — create order → obtain confirmUrl → (webview) → recheck.
- `BootstrapUseCase` — guest config + (if session) `/me`, apply gates.

Simple list/detail reads call the repository directly from the ViewModel (no UseCase).

---

## 5. Dependency injection

**Riverpod is the DI container** — no separate `get_it`. Construction graph:
- `dioProvider` builds Dio (flavor config + interceptors that themselves read `secureStoreProvider`, `authStateProvider`).
- `apiClientProvider` wraps Dio; repository providers depend on it; ViewModels depend on repositories.
- Flavor/config injected via `ProviderScope` overrides at app start (`main_<flavor>.dart` sets `AppConfig`).
- Tests: wrap the widget/unit under test in a `ProviderContainer`/`ProviderScope` with overridden repository/apiClient providers (fakes) — no global singletons to reset.

---

## 6. Session & global state

- `authStateProvider` (`unknown/guest/authenticated`) — the single source the router + UI react to. Set by the auth use cases + the refresh interceptor.
- `bootstrapProvider` (AsyncNotifier) — holds guest config + gates; refreshed on resume; drives Maintenance/Force-Update.
- `meProvider` (AsyncNotifier) — the authenticated `/v1/me` snapshot (user, devices, subscriptions, flags); cached + refreshed on Home enter / pull-to-refresh; the extensible source Home/Profile bind to.
- Theme/locale providers persist to prefs.

---

## 7. Granular providers & rebuild minimisation

The Devices area (and any complex feature) is split into **small, single-responsibility providers** — never one "god provider". A change in one provider rebuilds only its watchers.

- **One provider per sub-feature, scoped by id:** `deviceListProvider`, `deviceDetailProvider(id)`, `deviceStatsProvider(id,period)`, `deviceHistoryProvider(id)`, `commandStatusProvider(commandId)`, `barrierOpenProvider(deviceId)` — each `autoDispose` + `family` (see `FLUTTER_MODULES.md §Devices granular`). The live open/command poll updates its own provider only; the list/detail/stats never rebuild from it.
- **`select` for fine-grained watches:** widgets `ref.watch(provider.select((s) => s.field))` so a screen rebuilds only when the field it uses changes (e.g. the open button watches only the command `state`, not the whole snapshot).
- **`autoDispose`** screen state → freed on pop (memory + no stale rebuilds); keep-alive only infra + session (`authState`, `bootstrap`, `me`).
- **`family`** keys state by id so two device details don't share/clobber state.
- **Split read vs write:** the cached entity comes from the repository/cache; the screen ViewModel holds only the screen's transient state — so a background cache refresh doesn't churn unrelated screens.
- **Const widgets + `RepaintBoundary`** on list rows/heavy items; `ListView.builder`/slivers; no rebuild storms.

This keeps the live barrier-open polling (the most update-heavy flow) isolated, and large lists smooth, even as the device area grows.

---

## 8. State conventions

- All state objects are `freezed` immutable; `AsyncValue<T>` for async screens → maps directly to loading/data/error UI.
- ViewModels expose **intents** (`submit()`, `resend()`, `openBarrier()`, `loadMore()`) — the View never mutates state directly.
- No business logic in widgets; widgets `watch` state + call intents.
- One-shot effects (navigate, snackbar) are surfaced via state flags or a `ref.listen` on the ViewModel, handled in the View.
- Cross-cutting services (`analytics`, `crashReporter`, `featureFlag`) are `ref.read` from use cases/ViewModels at action boundaries — never from widgets.
