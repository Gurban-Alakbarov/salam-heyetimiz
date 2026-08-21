# CRASH REPORTING

> Planning only. A provider-agnostic crash/error-reporting abstraction — **not** bound to Firebase or Sentry. Adapter chosen later.

---

## 1. Abstraction

```
CrashReporter  (domain abstraction)
  recordError(Object error, StackTrace? stack, {bool fatal, Map context})
  recordFlutterError(FlutterErrorDetails details)
  log(String breadcrumb)             // non-fatal trail
  setUserId(String? id)              // backend id / hashed; null on logout
  setCustomKey(String key, Object value)   // flavor, route, flags (no PII)
        ▲
  ┌─────┴───────────────────────────────────────┐
  NoopCrashReporter   CrashlyticsCrashReporter   SentryCrashReporter
  (dev)               (future)                   (future)
```

- Choosing **Firebase Crashlytics** or **Sentry** later = one adapter; call sites unchanged.
- Could even fan out to multiple (e.g. Sentry for releases + a log sink in qa).

---

## 2. Wiring (planned, in `bootstrap.dart`)

- `FlutterError.onError` → `recordFlutterError` (framework errors).
- `PlatformDispatcher.instance.onError` → `recordError(fatal: true)` (uncaught async).
- `runZonedGuarded(runApp, (e, s) => recordError(e, s, fatal: true))` (zone errors).
- **Breadcrumbs:** the network layer logs non-fatal breadcrumbs for failed requests (method + path + status — **no body, no token**); navigation logs route changes as breadcrumbs; key actions (open, payment) add context keys.
- **Custom keys (no PII):** flavor, app_version, current route, active feature-flags, connectivity, last 1–2 user actions.

---

## 3. Relationship to Analytics

Both are fed by the **same central error pipeline** but serve different goals:
- `AnalyticsService.logEvent(error)` → an aggregated **product** signal (counts/funnels).
- `CrashReporter.recordError(...)` → the **engineering** signal (stack trace, breadcrumbs, device, fix).
The `Failure` mapper (`API_INTEGRATION.md`) decides: expected failures (422/401/offline) → analytics only; unexpected throwables/5xx/parse errors → crash reporter (non-fatal) + analytics.

---

## 4. PII & flavor policy

- **Scrub** tokens, email, phone, OTP, card data from messages, breadcrumbs, and custom keys.
- **User id** = backend numeric id or a hash; cleared on logout (`setUserId(null)`).
- **Flavors:** `Noop` in **dev**; real reporter **on** in **qa + prod** (so QA crashes are captured pre-release).
- Respect the analytics/consent toggle for any optional diagnostic upload.

---

## 5. Riverpod
`crashReporterProvider` exposes the composed reporter; the global handlers in `bootstrap.dart` use it; repositories/use cases may add breadcrumbs/context. It is initialised **before** `runApp` so early crashes are captured.

---

## 6. Long-term advantage
Crash visibility is in place from the first build (qa + prod), the vendor decision (Crashlytics vs Sentry) is deferred to a single adapter, PII safety + breadcrumb policy are centralized, and the same error pipeline cleanly splits product analytics from engineering crash data — giving fast, privacy-safe diagnosis without retrofitting error handling later.
