# FEATURE FLAGS

> Planning only. A flag system so every new module ships behind a switch and lights up when ready — **local in v1, Remote-Config-ready via an adapter.** Aligns with the **backend, which already returns flags** in `/v1/bootstrap` + `/v1/me` (`feature_flags`, `app.*`).

---

## 1. Flag catalog

| Flag | Default (v1) | Source | Flips on |
|---|---|---|---|
| `payments` | **on** | local + bootstrap | live (orders/checkout implemented) |
| `biometric` | **on** | local | live (local_auth) |
| `maintenance` | from backend | **bootstrap.app.maintenance_mode** | admin toggles Settings |
| `forceUpdate` | from backend | **bootstrap.app.force_update / min_version** | admin/version gate |
| `notifications` | **off** | local → backend later | backend notifications + push-token endpoint ships |
| `push` | **off** | local → backend later | backend push-token registration ships |
| `password` | **off** | **/me.has_password** + local | backend Security/password-login ships |
| `profileEdit` | **off** | local | backend profile-update endpoint ships |
| `family` | **off** | local → remote later | future "family members" module |
| `visitors` | **off** | local → remote later | future "visitor passes" module |
| `marketplace` | **off** | local → remote later | future module |
| `debug` | dev only | local (flavor) | dev/qa builds only |

> `email_otp`, `sms_login`, `registration` are **already returned by the backend** `feature_flags` — the app consumes them directly (e.g. `sms_login=false` → hide any SMS-login path).

---

## 2. Architecture — abstraction + composed sources

```
FeatureFlagService  (domain abstraction)
   bool isEnabled(FeatureFlag)
   T   value<T>(FeatureFlag, fallback)     // for typed/remote values
   Stream<FlagSnapshot> changes            // reactive (Riverpod)
        ▲ composes (priority order)
        │
  ┌─────┴───────────────────────────────────────────┐
  RemoteFlagSource(future)  BootstrapFlagSource(live)  LocalFlagSource(always)
  (Firebase Remote Config)  (/v1/bootstrap + /me)      (compile defaults + dev overrides)
```

- **LocalFlagSource** — hard defaults per flag + a dev-only override store (so QA can flip flags in a debug menu). Always present; the floor.
- **BootstrapFlagSource** — reads the backend `feature_flags` + `app.*` from the cached bootstrap/`/me` snapshot. **Already live** — no backend work needed.
- **RemoteFlagSource (future)** — a Firebase Remote Config adapter; added later **without touching call sites** (just register the source). 
- **Resolution priority:** `kill-switch/maintenance (backend) > remote > bootstrap > local default`. Backend maintenance/force-update always win.

---

## 3. Usage

- **Routing:** `NAVIGATION.md` guards read flags — a route behind `notifications`/`family` is unreachable (redirect/404) while off.
- **UI:** widgets gate on `ref.watch(featureFlagProvider(FeatureFlag.password))` — e.g. the "Set Password" tile is hidden until `password` is on.
- **Modules:** a whole feature module registers behind a flag; default **off** → invisible until flipped. New module = build it flagged-off, merge safely, flip when backend + QA ready.
- **Reactive:** flags come from a Riverpod provider; a bootstrap refresh (resume) can change a flag at runtime (e.g. maintenance) and the router/UI react.

---

## 4. Riverpod wiring (planned)

- `featureFlagServiceProvider` — the composed service (sources injected; LocalFlagSource always, BootstrapFlagSource fed by `bootstrapProvider`/`meProvider`).
- `featureFlagProvider(FeatureFlag)` — `bool` for a single flag (granular watch → minimal rebuilds).
- `flagsSnapshotProvider` — the resolved map (debug menu + analytics user-property).

---

## 5. v1 scope vs future
- **v1:** Local defaults + the **already-live backend bootstrap flags** + a debug override menu. No Remote Config dependency.
- **Future:** drop in the `RemoteFlagSource` (Firebase Remote Config) adapter — one class, registered in the composer; call sites unchanged. Enables % rollouts, A/B, kill-switches without an app release.

---

## 6. Long-term advantage
Every risky/incomplete feature ships dark and is enabled centrally; the backend can already gate the app (maintenance/force-update/feature_flags) **today**; future remote control is a one-adapter add; and dependent features (password, notifications, profile-edit) flip on automatically as their backend endpoints ship — **no re-architecture, no app release required to toggle.**
