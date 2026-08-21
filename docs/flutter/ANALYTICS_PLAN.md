# ANALYTICS PLAN

> Planning only. A provider-agnostic analytics abstraction from day one — **no provider chosen yet**; Firebase Analytics or PostHog plug in later as an adapter.

---

## 1. Abstraction

```
AnalyticsService  (domain abstraction)
  logEvent(AnalyticsEvent event)
  logScreenView(String screen, {Map params})
  setUserId(String? id)              // backend user id (or hashed); null on logout
  setUserProperty(String key, String value)
  reset()                            // on logout — clears identity
        ▲ fans out to N sinks (multi-adapter)
  ┌─────┴─────────────────────────────────────────┐
  NoopAnalyticsAdapter   FirebaseAnalyticsAdapter   PostHogAnalyticsAdapter
  (default / dev)        (future)                   (future)
```

- **Multi-sink:** `AnalyticsService` holds a list of `AnalyticsAdapter`s and forwards to all enabled ones → you can run Firebase + PostHog simultaneously or swap freely.
- **Default = Noop** (dev): events are logged to the console logger only, nothing leaves the device until an adapter is registered.
- **Adding a provider = writing one adapter** + registering it (flavor/flag). Zero call-site changes.

---

## 2. Event catalog (typed)

Events are a sealed/`freezed` `AnalyticsEvent` union (typed params, no stringly-typed misuse):

| Event | Params (no PII) |
|---|---|
| `app_open` | cold/warm, app_version |
| `screen_view` | screen_name (auto via nav observer) |
| `registration_started` | — |
| `register` | success/fail, fail_reason |
| `otp_verify` | flow(register/login), success/fail, fail_code |
| `resend_otp` | flow |
| `login` | success/fail |
| `logout` | — |
| `barrier_open` | device_id(hashed/opaque), result(opened/failed/expired/cooldown), latency_ms |
| `payment_started` | order_id, amount_minor, currency |
| `payment_success` | order_id |
| `payment_failed` | order_id, reason |
| `subscription_renew` | subscription_id, auto_renew |
| `force_update_shown` | min_version |
| `error` | domain, code, screen (no message PII) |

> **PII rules:** never log email, phone, OTP code, token, or card data. User id = the backend numeric id (or a hash); device id surfaced as an opaque/hashed value. Event params go through a whitelist; the adapter scrubs anything unexpected.

---

## 3. Wiring (planned, no code)

- **Screen views:** a `go_router` `NavigatorObserver` (`AnalyticsNavObserver`) auto-emits `screen_view` on route changes — no per-screen boilerplate.
- **Funnels:** key flows call `logEvent` at the **ViewModel/UseCase** boundary (not in widgets) — e.g. `RegisterUseCase` emits `register`, `OpenBarrierUseCase` emits `barrier_open` with the terminal result + latency, `CheckoutUseCase` emits payment events.
- **Errors:** the central error pipeline (`API_INTEGRATION.md` Failure mapper + global handler) emits `error` events (and the crash reporter records throwables — see `CRASH_REPORTING.md`).
- **Identity:** on login/verify → `setUserId`; on logout → `reset()`. Locale/flavor/feature-flags set as user properties for segmentation.

---

## 4. Riverpod
`analyticsProvider` exposes the composed `AnalyticsService`; use cases/ViewModels `ref.read` it to log. The nav observer + global error handler are wired in `bootstrap.dart`.

---

## 5. Consent & privacy
Analytics is **opt-in-aware**: gate non-essential analytics behind a settings toggle + the privacy posture (the backend already models consent kinds). In v1, ship with Noop (no external sink) until a provider + consent flow is decided — so there's **no privacy exposure before the product decision**, yet all instrumentation points already exist.

---

## 6. Long-term advantage
Instrumentation is written **once, now** (typed events at the right boundaries); choosing Firebase vs PostHog later is a single adapter; you can A/B providers or run both; PII safety is centralized; and product funnels (register→verify→home, open success rate, payment conversion) are measurable from launch without retrofitting tracking calls across the app.
