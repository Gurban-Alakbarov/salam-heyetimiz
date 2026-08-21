# RELEASE PLAN — Testing · Performance · Flavors · CI · Store

> Planning only — no project, no CI configured.

---

## 1. Build flavors / environments

| Flavor | API base | Purpose | Notes |
|---|---|---|---|
| **dev** | local / staging | Day-to-day dev | verbose logging, mock-able API, no crash reporting |
| **qa** | staging | QA / pre-prod testing | logging on, test FCM, sandbox payments |
| **prod** | `https://api.salamheyetimiz.com` | Production | logging off, obfuscated, crash reporting on |

- Flavor selected via **`--dart-define`** (`APP_ENV=prod`) + per-platform flavors (Android product flavors, iOS schemes/xcconfig). Entrypoints `main_dev.dart / main_qa.dart / main_prod.dart` set `AppConfig` and inject it through `ProviderScope` overrides.
- Distinct **applicationId/bundleId** per flavor (`...dev`, `...qa`, prod) so all three install side-by-side; distinct app name + icon per flavor.
- Secrets (FCM config, pin set) per flavor; never commit prod secrets.

---

## 2. Versioning & build numbers

- **Version:** semantic `MAJOR.MINOR.PATCH` (e.g. `1.0.0`) — the value compared against the backend `bootstrap.min_version` / `latest_version` for force-update.
- **Build number:** monotonic, **set by CI** (e.g. CI run number) — never hand-edited.
- A single source (`pubspec.yaml` version) drives both platforms; CI injects `--build-number`.

---

## 3. Testing strategy

| Layer | Scope | Tooling |
|---|---|---|
| **Unit** | repositories (mapped DTO→Entity), mappers, use cases, notifiers (with a fake repository), error mapping, validators, money/date formatting | `flutter_test` + `mocktail` |
| **Widget** | screens with overridden Riverpod providers — render loading/data/empty/error states; form validation; OTP field; the screen-state matrix | `flutter_test` + `ProviderScope` overrides |
| **Golden** | design-system components (buttons/fields/dialogs/states) in light + dark, az/en/ru | `golden_toolkit`/`alchemist` |
| **Integration / E2E** | critical flows against a **mock API server** (or qa): register→verify→home, login→verify, barrier-open state machine, checkout→return→recheck, refresh-on-401, force-update gate | `integration_test`/`patrol` |
| **Contract** | DTO ↔ OpenAPI: golden JSON fixtures derived from the (reconciled) `openapi.json` so a backend shape change breaks a test | fixture-based |

**Coverage targets:** repositories + use cases + error mapping ~high (these carry the logic); UI ~ smoke + golden. **Priority test:** the **barrier-open state machine** (queued→…→opened/failed/expired, offline, 429 cooldown) and the **auth/refresh** path — the two riskiest flows.

**Mock API:** a local mock (e.g. `dio` mock adapter or a small mock server) seeded from the OpenAPI examples so the app can be developed/tested without hitting prod. (Reinforces the value of reconciling the spec — `docs/reviews/API_REVIEW.md TD-1`.)

---

## 4. Performance plan

| Area | Plan |
|---|---|
| **Cold start** | minimal Splash work: init DI + storage, render from cached bootstrap/`/me`, revalidate in background; defer heavy init; `--obfuscate` + tree-shake icons |
| **Warm start** | resume → revalidate gates + `/me` + drain outbox; keep it light |
| **Image cache** | `cached_network_image` (avatars/logos), sized requests, placeholders/skeletons |
| **Lists** | cursor pagination + **infinite scroll**; `ListView.builder`/slivers; cache first page (offline) |
| **Lazy loading** | `autoDispose` providers free screen state on pop; lazy Hive boxes; defer non-critical features |
| **Background refresh** | on resume + connectivity-regained + pull-to-refresh (stale-while-revalidate) — no aggressive timers |
| **Memory** | dispose controllers + cancel in-flight Dio requests on screen dispose; bounded barrier-poll loop |
| **Jank** | const widgets, `RepaintBoundary` on heavy items, skeleton instead of spinners, avoid rebuild storms (granular `select`) |
| **Binary size** | split-per-abi (Android), tree-shake, SVG over raster where possible |

---

## 5. CI/CD

- **CI:** GitHub Actions **or** Codemagic (Codemagic is Flutter-native + easy signing). Pipeline per PR: `flutter analyze` → `dart format --set-exit-if-changed` → `flutter test` (unit+widget+golden) → build (dev) artifact. On `main`/tag: integration tests + qa/prod builds.
- **Signing:** managed in CI (Android keystore + iOS certs/profiles via Fastlane match or Codemagic codesigning) — never on a dev machine.
- **Quality gates:** analyze + format + tests must pass to merge; coverage reported.

---

## 6. Store release (Fastlane)

- **Fastlane** lanes: `beta` (Android internal track / iOS TestFlight) and `release` (production, staged rollout). `match` for iOS signing; `supply` for Play; `deliver`/`pilot` for App Store/TestFlight.
- **Staged rollout:** Play % rollout (5→20→50→100); iOS phased release. Watch crash-free rate + key funnels (register→verify→home, open success) before widening.
- **Force-update lever:** because the app honors `bootstrap.min_version`/`force_update`, a bad release can be **force-migrated** by bumping `min_version` server-side (admin Settings) — a powerful safety net; document the runbook.
- **Store assets:** localized (az/en/ru) listings, screenshots from golden/integration runs, privacy nutrition labels (data collected: email/phone for auth, device id for push) — coordinate with the backend privacy posture.

---

## 7. Release readiness checklist (mobile)

- [ ] OpenAPI spec reconciled (backend TD-1) → mock server + contract tests seeded from it.
- [ ] All flavors build + sign in CI; prod obfuscated; version/min_version aligned with backend.
- [ ] Critical-flow integration tests green (register/verify, login, open, checkout, refresh, force-update).
- [ ] FCM gated off (until backend push ships) — no crash if absent.
- [ ] Secure storage + logout wipe verified; no token/PII in prod logs.
- [ ] Force-update + maintenance gates verified against live `bootstrap`.
- [ ] Crash reporting (Crashlytics/Sentry) wired in qa/prod.
- [ ] Store listings localized; staged rollout plan + force-update runbook documented.

---

## 8. Phasing recap

- **P1 (launch):** auth/registration, bootstrap, home, devices, **barrier open**, orders/checkout, subscriptions, profile (view), security (biometric), settings, support, system gates, az/en/ru, obfuscation, CI + flavors + staged rollout.
- **P2:** cert pinning, screenshot protection, root-detection, push (when backend ships), profile edit (when endpoint ships).
- **Future:** password login, notifications inbox, and any newly-implemented backend domains — absorbed via the extensible `/v1/me` + feature flags **without re-architecting**.
