# Salam Həyətimiz — Implementation Roadmap

**Version:** 1.0
**Date:** 2026-06-09
**Status:** Draft for sign-off
**Source documents:** All v1.1 / v1.0 specs in `docs/` (see CHANGELOG.md).
**Team assumption:** **One senior developer + Claude Opus 4.7 as a pair.**

This roadmap sequences the v1.1 plan into executable phases, sized for a single engineer working with an AI pair. Every phase declares its goal, deliverables, dependencies, acceptance criteria, effort estimate, and the concrete Opus workflow that accelerates it.

---

## 0. Working Assumptions

| Assumption | Value | Source / rationale |
|---|---|---|
| Team size | 1 senior dev + Opus 4.7 | Stated |
| Average effective velocity vs. solo (no AI) | ≈ 1.8× | Boilerplate 3×, business logic 1.5×, integration 1.2×, mobile UI 2× — weighted by phase |
| Calendar week ≈ engineering days | 4 days (allow 1 d for meetings, support, context-switches) | Realistic |
| External dependencies (Kapital sandbox, hardware vendor, carriers) | Will lag responses by days, sometimes weeks | Factored into Phase 0 and Phase 1 buffers |
| App-store review windows | 1–4 days iOS, 1 day Android | Phase 3 buffer |
| Pen-test vendor lead time | 3–4 weeks to schedule + 1 week to run | Booked at Phase 1 start, runs in Phase 3 |
| GSM hardware procurement | 2–3 weeks lead time | Ordered Phase 0 |
| Dual-GSM gateway deployment (CRIT-03) | Hardware + 2 facilities | Bottleneck on infra; ordered Phase 0 |
| Claude Opus available throughout | Yes | Stated |
| Plan-of-record | v1.1 documents in `docs/` | Audit-resolved |

**Total to soft launch:** approximately **30–34 weeks** (≈ 7–8 calendar months). Detailed per-phase sizing below.

---

## 1. Phase Map

| Phase | Name | Calendar | Engineering days | Cumulative |
|---|---|---|---|---|
| 0 | Validation & Procurement | 3 weeks | 12 d (much of it waiting) | 3 wk |
| 1A | Project scaffold + Identity + Catalog + Users | 3 weeks | 12 d | 6 wk |
| 1B | Devices + DeviceComm driver layer + Open pipeline | 3 weeks | 12 d | 9 wk |
| 1C | Payments + Subscriptions + Notifications scaffold | 3 weeks | 12 d | 12 wk |
| 1D | Audit, CI invariants, HA Redis, dual GSM gateway, admin shell | 3 weeks | 12 d | 15 wk |
| 2A | Flutter scaffold + Auth + Home + Open flow | 3 weeks | 12 d | 18 wk |
| 2B | Subscriptions + Checkout + Payment WebView + Receipts | 3 weeks | 12 d | 21 wk |
| 2C | Roster + Invitations + Notifications + Profile + Tech mode | 3 weeks | 12 d | 24 wk |
| 3 | Hardening, app-store submission, soft launch | 6 weeks | 24 d | 30 wk |
| 4 | Scale & Polish (open-ended) | n/a | n/a | post-launch |

Phase 1 is split into four 3-week chunks (1A–1D) because the audit-revised Phase-1 scope is too large for a single 5-week sprint when staffed solo. Phase 2 is similarly chunked.

---

## 2. Cross-Cutting Workflows With Opus

These workflows apply across all phases and are referenced from individual phase sections. They are the highest-leverage uses of Opus 4.7 for this solo-dev setup.

### W-1 — Multi-agent scaffolding workflow

**When:** Each domain module is being created or substantially extended.
**Pattern:** Use `Workflow` to spawn one `general-purpose` agent per domain module. Each agent reads the relevant spec sections and produces the module's models, services, actions, events, exceptions, and policies as a coherent unit. Single-message fan-out keeps the dev unblocked during the slowest module.
**Why solo+AI:** A team would parallelize by person; we parallelize by agent.
**Caveat:** Agents must be briefed with file paths and the relevant doc sections; otherwise output drifts from spec.

### W-2 — Plan-mode design review for security-critical paths

**When:** Designing the open-permission check, payment-callback pipeline, recovery-code consumption, JWT key rotation, anonymisation job.
**Pattern:** Enter Plan mode, write the plan, have Opus adversarially critique it before exiting Plan mode. Capture the dialogue as a decision record in `docs/decisions/`.
**Why solo+AI:** Pair-programming acts as the "second pair of eyes" that solo devs lack.

### W-3 — Test scaffolding from spec tables

**When:** Most domains. Especially `errors.*`, `validation.*`, refund pro-rata, subscription edge cases (§13.9 of tech spec).
**Pattern:** Hand the spec table to Opus; ask it to produce a Pest test class with one test per row. Dev fills in assertions; Opus drafted them are 80 % correct on first pass.
**Why solo+AI:** Tests are the most boring high-leverage work; AI productivity multiplier is highest here.

### W-4 — OpenAPI-driven code generation

**When:** Controllers, FormRequests, Resources, Flutter API client.
**Pattern:** Use Opus to read `openapi/v1.yaml`, produce per-endpoint scaffolds. Validate output against `docs/openapi/validate.php`.
**Why solo+AI:** The spec IS the contract; AI converts contract to code 1:1 with high fidelity.

### W-5 — Spec ↔ code drift detection

**When:** Before every PR merge.
**Pattern:** Spawn an `Explore` subagent: "Does this PR change behaviour that should be in a v1.x doc but isn't?" If yes, the PR doesn't merge until the spec is updated.
**Why solo+AI:** Catches the "I'll update docs later" trap. The AI is the discipline a solo dev lacks.

### W-6 — Translation generation

**When:** Phases 1–3 every time a string is added.
**Pattern:** Source string in `lang/az/`. Opus produces draft `en` and `ru` versions with placeholder preservation and plural-form coverage. Native-speaker reviewer (off-team) reviews monthly batches via PR.
**Why solo+AI:** Translation review backlog kills solo progress; AI keeps the pipeline filled.

### W-7 — Runbook authoring

**When:** Phase 1 and Phase 3.
**Pattern:** Each "the runbook says…" reference in the spec gets its own runbook file. Opus drafts from the spec; dev verifies steps actually work on staging.
**Why solo+AI:** Runbooks are tedious to write but cheap with AI — and they're what saves the dev at 2 AM during the first incident.

### W-8 — Adversarial verification for finished features

**When:** End of each Phase 1 sub-phase, end of Phase 2.
**Pattern:** Use the `/code-review` skill at `high` effort. Surface findings, triage, fix or backlog.
**Why solo+AI:** Replaces team code review with an adversarial reader.

---

## 3. Phase 0 — Validation & Procurement (3 weeks)

### Goal
Validate every Phase-1 prerequisite empirically so coding does not start on assumptions that can fail in production. Order long-lead hardware. Lock the seven Fix-Now decisions from the Audit Resolution Plan §4.3.

### Deliverables
1. **Phase-0 acceptance gates G1–G8** (tech spec §19) green or owner-waived in writing.
2. **CLIP validation report per AZ operator** (`docs/phase0/clip-validation-{azercell,bakcell,nar}.md`) with video + logs.
3. **Kapital sandbox transcript**: one full purchase + one full refund + a deduplicated callback storm.
4. **Procurement orders placed**: two GSM-modem gateway appliances, SIMs across two operators, Redis Sentinel infra.
5. **Provider contracts signed or LoIs**: SMS provider for AZ phone numbers; voice termination route; hosting.
6. **Decision records** in `docs/decisions/` for: open-permission cache (HIGH-06), `device_users` uniqueness (HIGH-07), English admin date format, mobile fallback nuance, TMS deferral.
7. **`admin_users.preferred_language` column added** (covers Localization Spec Appendix E item).
8. **Project repository initialised**: Laravel 12 + Flutter app + admin Blade boilerplate stubs (just `composer create-project` level), CI pipeline running placeholder lint.

### Dependencies
- Vendor responses (carriers, Kapital Bank, hosting). The dev has limited control of this calendar.
- Counsel review of AZ tax-receipt obligations (audit MED-12).

### Acceptance Criteria
- Eight Phase-0 gates pass or each has a named owner-waiver document.
- Hardware purchase orders placed; ETAs known.
- A one-page "Phase-1 entry decision" memo countersigned by tech lead and product.
- Repository CI runs and is green.

### Estimated Effort
- **Engineering days: 12** (the rest of the 3 weeks is waiting on external parties).
- Bulk of dev time is: writing the CLIP test artisan command, the Kapital sandbox harness, and the decision records.

### Claude Opus Workflow
- **Web research** (`WebFetch`, `Agent(general-purpose)`): pull Kapital e-Commerce API docs, GSM modem datasheets, AZ carrier interconnect behaviour notes.
- **Test-harness scaffolding** (`Agent(general-purpose)`): generate the `gsm:test-clip` and `kapital:smoke` artisan commands. Dev runs them against real hardware.
- **Plan-mode reviews (W-2)** for: voice-gateway topology choice (SIP termination vs on-prem modem), Redis HA approach.
- **Decision-record templates**: Opus drafts a one-page-per-decision template; dev fills in the empirical findings.
- **Runbook seeds (W-7)**: skeleton runbooks for cert-pin rotation, JWT key rotation — to be fleshed out in Phase 1.

### Risks This Phase Defuses
- CRIT-01 (CLIP carrier rewriting) — validated empirically.
- CRIT-03 (gateway capacity) — hardware ordered before coding starts.
- CRIT-04 (key rotation) — runbook design begun before keys exist.

---

## 4. Phase 1 — Backend Foundations (12 weeks; 4 × 3-week chunks)

Backend MVP through to "a real device can be opened from Postman by an authenticated test user end-to-end" (the v1.1 spec exit criterion), expanded with the audit-resolved scope.

### Phase 1A — Project Scaffold + Identity + Catalog + Users (3 weeks)

**Goal**
Establish the foundation: a deployable Laravel app with auth, lookups, and user management. Get all infrastructure rails (CI/CD, observability, environments) running before product features land.

**Deliverables**
1. Laravel 12 project scaffolded per `BACKEND_ARCHITECTURE.md` §3 folder layout.
2. CI pipeline: lint (PHPStan L8, Larastan), Pest tests, OpenAPI validator, GRANT integrity check (HIGH-13 / CI-1).
3. Environments: dev, staging deployed with HTTPS, basic Nginx, PHP-FPM.
4. Identity & Auth module:
   - OTP request/verify, refresh tokens, rotation
   - JWT with `kid` claim, JWKS endpoint (CRIT-04)
   - Admin email + password + TOTP **+ recovery codes** (CRIT-09)
5. Catalog module: `sim_operators`, `device_models`, `regions` (CRUD + seed data).
6. Users module: profile CRUD, consent recording, soft-delete with immediate anonymisation (HIGH-12).
7. Migrations 00_system through 03_identity_auth executed.
8. Initial set of `lang/{az,en,ru}/` files with the `errors.*` and `validation.*` baselines.

**Dependencies**
- Phase 0 complete (key decisions locked).
- Hosting provisioned (dev + staging at minimum).

**Acceptance Criteria**
- New user can sign up, verify OTP, receive tokens, refresh tokens, log out — via curl against staging.
- Admin can log in with email + password + TOTP, get an admin JWT, log out.
- Admin can lose their phone and recover via a single-use code.
- `php artisan migrate --pretend` shows clean migrations on a blank DB.
- `php docs/openapi/validate.php` passes after generation.
- Grants CI check fails the build if anyone grants UPDATE on `audit_log`.
- All 21 of Phase-1A's tests are green; ≥ 70 % line coverage on Auth + Users modules.

**Estimated Effort**
12 engineering days. Most days are boilerplate-heavy; Opus shines.

**Claude Opus Workflow**
- **W-1 (multi-agent scaffolding)**: one agent per module (Auth, Catalog, Users). Each reads the relevant `BACKEND_ARCHITECTURE.md` §14 entry and produces models / services / actions / events.
- **W-4 (OpenAPI-driven scaffolding)**: generate controllers for `requestOtp`, `verifyOtp`, `refreshToken`, `logout`, `getMe`, `updateMe`, etc.
- **W-2 (plan-mode review)** for the JWT-with-`kid` design, recovery-code consumption transaction, and anonymisation algorithm.
- **W-7 (runbooks)**: complete drafts of `jwt-mobile.md`, `jwt-admin.md`, `cert-pin.md`.
- **W-6 (translations)**: AZ source strings written, Opus generates EN + RU drafts.
- **W-8 (adversarial review)** at end of chunk against `/code-review high`.

---

### Phase 1B — Devices + DeviceComm + Open Pipeline (3 weeks)

**Goal**
The headline feature backend-only: a real GSM device can be opened from an authenticated API call. Includes the driver layer with fallback policy, the open command pipeline with attempts/feedback, the whitelist outbox with priority/seq/burst guard.

**Deliverables**
1. Devices module: registration, assignment, state machine (`unassigned`/`active`/`suspended`/`disabled`/`decommissioned`), location, SIM lifecycle columns (HIGH-04 placeholder).
2. Roster module: `device_users`, `device_user_history`, capacity enforcement (HIGH-05), STORED uniqueness with CI concurrency test (HIGH-07).
3. Invitations: create / accept / decline / expire / SMS dispatch.
4. **DeviceComm driver layer** (`DeviceDriver` interface, `ClipDriver`, `SmsDriver`, `HybridDriver`, `MqttDriver` stub) per `BACKEND_ARCHITECTURE.md` §14.6.
5. `VoiceGatewaySelector` with health-aware round-robin and circuit breaker (CRIT-03).
6. `OperatorFallbackPolicy` reading the Phase-0 validated `operator_default_drivers` map (CRIT-01).
7. `DispatchOpenCommand` action with driver-fallback (HIGH-03), persisting `open_command_attempts`.
8. Whitelist outbox: `WhitelistOutboxService`, `priority`/`seq` ordering, burst guard, per-device serialisation.
9. Cooldown enforcement (per-user-device and per-device global) via Redis locks.
10. `OpenCommandService::request` end-to-end → Postman demo against a real device.

**Dependencies**
- Phase 1A complete.
- At least one physical GSM device + SIM on hand.
- One GSM gateway operational (the second arrives during Phase 1D).

**Acceptance Criteria**
- A real device opens within p95 ≤ 5 s (CLIP) or ≤ 15 s (SMS) under the curl harness.
- Cooldown violations return `429 cooldown` with `Retry-After`.
- Driver fallback (CLIP → SMS) triggers on transient failure; both attempts visible in `open_command_attempts`.
- Whitelist drain test: add 8 users, all eventually `synced`; admin can see queue state.
- Concurrency test for `device_users` uniqueness passes (HIGH-07 Plan A or fallback to Plan B captured in a decision record).
- DeviceComm metrics exposed (open count, success rate, p95 latency) via Prometheus.

**Estimated Effort**
12 engineering days. GSM hardware quirks consume more days than the spec suggests — buffer accordingly.

**Claude Opus Workflow**
- **W-1**: one agent per (Devices, Roster, DeviceComm) module.
- **W-2 (plan-mode)**: design the `DispatchOpenCommand` action — cooldown lock acquisition order, attempt-row state machine, idempotency interplay. This is the highest-stakes design in the backend; do not skip the adversarial review.
- **W-3 (test scaffolding)**: produce Pest tests for every row of tech spec §12.10 failure-modes table.
- **W-2 (plan-mode)**: `DriverResolver` + `OperatorFallbackPolicy` lookup — the policy-vs-device-override conflict resolution.
- **W-7 (runbooks)**: "device went silent" troubleshooting; whitelist drift recovery (manual variant of HIGH-02).
- Use `Workflow` to scaffold all four driver implementations in parallel; dev integrates against real hardware sequentially.

---

### Phase 1C — Payments + Subscriptions + Notifications scaffold (3 weeks)

**Goal**
Full payments pipeline against Kapital sandbox + subscription lifecycle with the v1.1 refund algorithm + notification dispatch primitives.

**Deliverables**
1. Payments module:
   - `OrderService::create` with device-sale ↔ device link enforcement (HIGH-09)
   - Kapital integration (`KapitalBankClient`)
   - `PaymentCallbackController` + `CaptureRawBody` middleware + `VerifyKapitalSignature` (CRIT-07)
   - `ProcessPaymentCallbackJob` with `getOrderStatus` cross-check + PENDING handling
   - `PaymentLogger` with allowlist + encrypted columns (HIGH-15)
   - `RefundService::executeApprovedRefund` with pro-rata algorithm (HIGH-08, full refund only at MVP)
   - `OrderReconciler` hourly job
2. Subscriptions module:
   - Lifecycle (`pending_payment` → `active` → `expired` / `cancelled` / `refunded`)
   - `subscription_periods` history per HIGH-18 derivation
   - Expiry sweep + reminder schedule
   - §13.9 edge-case handling (CRIT-02) returning the per-caller `suspension_reason`
3. Notifications module:
   - `NotificationDispatcher`, `TemplateRenderer`
   - `notification_templates` + `_locales` seeded
   - Push provider client (FCM) wired
   - SMS dispatch wired
4. Listeners wired:
   - `OrderPaid` → activate sub + notify
   - `SubscriptionExpired` → notify + whitelist remove
   - `DeviceUserAdded` → audit + notify + whitelist add
5. The `errors.*` and `validation.*` translation keys reach full coverage.
6. CI scanner job `PaymentLogsScannerJob` running daily on staging.

**Dependencies**
- Phase 1B complete.
- Kapital sandbox credentials.
- FCM project created.

**Acceptance Criteria**
- End-to-end sandbox purchase: order created → bank redirect → callback verified → sub activated → push receipt sent.
- Full refund of an `active` sub flips status to `refunded`, whitelist removal job runs.
- Partial-refund algorithm has 100 % unit-test coverage (UI to ship in Phase 4).
- Subscription expiry sweep correctly transitions accounts and emits notifications.
- §13.9 edge cases each have a Pest test (one per table row).
- Payment-logs scanner finds zero PAN-like patterns after 24 h of sandbox traffic.

**Estimated Effort**
12 engineering days. The callback hardening + reconciler is the most error-prone surface; buffer 2 d for debugging.

**Claude Opus Workflow**
- **W-2 (plan-mode)**: payment callback flow with all three foot-guns (raw body, IP-list, PENDING) addressed. Use Opus to red-team the design — "what if the proxy strips trailing whitespace? what if Kapital double-sends from two IPs simultaneously?"
- **W-4 (OpenAPI-driven)**: generate `createOrder`, `getOrder`, `paymentCallback`, `submitOpenFeedback` controllers.
- **W-3 (test from spec)**: generate Pest tests from the tech spec §14.5.1 worked example + §13.9 edge cases.
- **W-1 (multi-agent)**: simultaneously scaffold OrderService, RefundService, SubscriptionService, NotificationDispatcher.
- **W-7 (runbooks)**: "callback storm" runbook; "stuck order" runbook; "refund-by-DB-surgery" runbook (last-resort).
- **W-5 (drift)**: every PR runs an `Explore` agent: "Does this change the payment contract described in `TECHNICAL_SPECIFICATION.md` §14 or `openapi/v1.yaml`? If yes, did the doc update?"

---

### Phase 1D — Audit, CI invariants, HA Redis, Dual GSM, Admin shell (3 weeks)

**Goal**
Production-shape infrastructure and the admin Blade shell. The features built in 1A-1C must now run on the deployment they will run on in production.

**Deliverables**
1. **Redis Sentinel** deployed in staging (one primary + two replicas + three sentinels) per CRIT-05.
2. Logical-DB split on Redis per Backend Arch §11.1.1.
3. **Second GSM gateway** operational in staging; `VoiceGatewaySelector` failover demonstrated.
4. Audit module:
   - `audit_log` partitioned (12 forward months)
   - Generic `RecordAuditFromEvent` listener
   - Audit search admin endpoint
5. **CI invariant checks** (Backend Arch §15.1):
   - `ci:grants:audit-log-immutable`
   - `ci:openapi:resources-match`
   - `ci:openapi:operationIds-unique`
   - `ci:device-users-uniqueness`
   - `ci:payment-logs:no-pan`
   - `ci:i18n:error-codes-cover` (Localization Spec §7.2)
6. **Horizon configured** with the named queues from Backend Arch §9 (sizing per single-dev modest scale).
7. **Admin Blade shell**: login (A-01), 2FA (A-02 with recovery), dashboard skeleton (A-10), devices list (A-30), users list (A-20), settings (A-80). Just enough to operate Phase 2 testing.
8. Smoke load test at 10 RPS open commands; capture p50/p95 against the dual gateway.
9. Phase-1 exit demo: end-to-end purchase + open + refund + reschedule via admin against staging.

**Dependencies**
- Phase 1C complete.
- Two GSM gateways delivered and racked.
- Redis Sentinel licence / managed-Redis budget approved.

**Acceptance Criteria**
- Kill one Redis node → no observable user-visible degradation; failover < 30 s.
- Kill one GSM gateway → open commands route to the surviving gateway transparently.
- All six CI checks fail builds correctly when the invariants are broken (each tested with a deliberate-break PR that gets reverted).
- Admin can view orders, refunds, devices, users; manage settings; search audit log.
- 10 RPS for 60 s: success rate ≥ 99 %, p95 ≤ 6 s (CLIP) / 18 s (SMS).
- Phase-1 exit demo passes in front of product.

**Estimated Effort**
12 engineering days. Infra work that can't be AI-shortcut consumes ~5 d.

**Claude Opus Workflow**
- **W-1**: parallel scaffolds for the admin Blade pages (one agent per module).
- **W-7 (runbooks)**: complete the Phase-1 set — Redis failover, gateway failover, partition roll, GRANT verification.
- **W-3 (test from spec)**: generate the deliberate-break PRs for each CI invariant.
- **`/code-review high`** as the final gate before declaring Phase 1 done.
- **W-5 (drift)**: full-repo `Explore` pass: "List every place where production behaviour differs from the v1.1 spec docs."

---

## 5. Phase 2 — Mobile + End-to-End User Journey (9 weeks; 3 × 3-week chunks)

The Flutter app and the user-facing journey end-to-end against the Phase-1 backend.

### Phase 2A — Flutter Scaffold + Auth + Home + Open Flow (3 weeks)

**Goal**
A user can install the app, sign up, see their device, and open it. The smallest possible mobile path that proves the system works for the end user.

**Deliverables**
1. Flutter project scaffolded per UI/UX §10.4 tech notes (Riverpod, dio, secure storage, biometric, FCM, deep links).
2. Theming + design tokens.
3. **i18n setup** per Localization Spec §4.2 / §6 — ARB bundles + `LocaleAware` helper + sync script.
4. Auth screens S-01 to S-08 (splash, locale picker, onboarding, phone, OTP, profile, consent, biometric enroll).
5. Home + device detail + open flow (S-10, S-11, S-12 with the CLIP/Hybrid copy distinction per CRIT-06).
6. WebSocket integration with polling fallback.
7. Push token registration + permission flow.
8. Open command feedback prompt (CRIT-06) + `submitOpenFeedback` integration.
9. Cert-pinned API client with two-pin support (CRIT-08).

**Dependencies**
- Phase 1 complete.
- Test devices on hand for both Android and iOS.

**Acceptance Criteria**
- New install → OTP → home → tap a pre-provisioned test device → gate physically moves.
- Cooldown UX shows the correct countdown.
- Biometric prompt blocks the open call until passed.
- "Did the gate open?" feedback prompt appears for CLIP-only devices and persists.
- Locale switch works without restart.
- Cert-pin verified by running the app against a MITM proxy — connection must fail.

**Estimated Effort**
12 engineering days. Flutter benefits massively from Opus widget generation.

**Claude Opus Workflow**
- **W-4 (OpenAPI-driven)**: Opus generates a typed Dart API client from `openapi/v1.yaml` (via swagger codegen + hand-touches).
- **W-1 (multi-agent)**: simultaneous scaffolds for the auth screens (S-04, S-05, S-06, S-07, S-08).
- **W-6 (translations)**: every string added → instant EN + RU drafts.
- **W-2 (plan-mode)**: the open-flow state machine and WebSocket-with-polling-fallback design.
- **Visual review**: use `mcp__Claude_in_Chrome__*` tooling against a running emulator to verify rendering on actual screens (when feasible).

---

### Phase 2B — Subscriptions + Checkout + Payment + Receipts (3 weeks)

**Goal**
The user can pay for a subscription end-to-end in production-grade flow.

**Deliverables**
1. Subscription screens S-30, S-31.
2. Checkout S-32; payment WebView S-33; payment result S-34 with the v1.1 return-URL-is-hint logic (HIGH-16).
3. Order list S-32 sibling; order detail screen.
4. Receipt rendering inside app (PDF deferred to Phase 4).
5. Renewal flow end-to-end.
6. Reminder push notifications received and routed to subscription detail.
7. Indeterminate-payment recovery path verified.

**Dependencies**
- Phase 2A complete.
- Kapital sandbox still active.

**Acceptance Criteria**
- New owner can: sign up → purchase device + main sub → open device. All in production-shaped flow against staging.
- Renewal flow: D-7 push arrives, user taps, renews, sees new `ends_at`.
- Indeterminate state: kill mobile mid-3DS, return → app correctly polls and resolves status.
- Return-URL tampering (manual: open `salam://payment/return?orderId=<other-user-id>`) → app shows 403/fallback path, never another user's data.

**Estimated Effort**
12 engineering days.

**Claude Opus Workflow**
- **W-1**: parallel scaffolds for the subscription / checkout / result screens.
- **W-3 (test from spec)**: golden tests for each `Order.status` state's rendering.
- **W-2 (plan-mode)**: deep-link handling state machine for the payment return URL.
- **`Workflow` for adversarial security review** of the payment WebView + return-URL handling.

---

### Phase 2C — Roster + Invitations + Notifications + Profile + Tech mode (3 weeks)

**Goal**
The owner-side and administrative experiences are complete.

**Deliverables**
1. Roster screens S-15, S-16, S-17 with the v1.1 provisioning chip (HIGH-01) and capacity guard (HIGH-05).
2. Invitation accept/decline (S-18) + deep-link handling.
3. Notification inbox + detail + settings (S-40, S-41, S-42).
4. Profile screens S-50–S-58, including:
   - Successor-required state in S-56 (HIGH-10)
   - Data export request (S-55)
   - Account deletion with anonymisation (S-56)
5. Technical mobile mode T-01 to T-06 (device provisioning).
6. Help / About / Support links.
7. Maintenance + Update-required screens.

**Dependencies**
- Phase 2B complete.
- Admin Blade panel can issue invitations and roster changes.

**Acceptance Criteria**
- Owner can invite a friend by phone; the friend gets SMS deep-link; friend installs, accepts, opens the gate.
- Roster capacity exceeded → owner sees server-side rejection with the localized capacity message.
- Account deletion: owner with active invitee subs is correctly blocked with `successor_required`; clean owner self-deletes successfully.
- Technical mobile mode can register a new device, ping, assign, open.
- Notification inbox renders all template types and their deep links.

**Estimated Effort**
12 engineering days.

**Claude Opus Workflow**
- **W-1**: per-screen scaffolds.
- **W-6**: per-screen translation batches.
- **W-3**: tests for the §13.9 edge cases now realisable end-to-end through the UI.
- **W-2 (plan-mode)**: deep-link routing (invitation tokens, payment returns, push deep links) — one design across all three.

---

## 6. Phase 3 — Hardening + Soft Launch (6 weeks; 24 engineering days)

### Goal
Take the Phase-2 product from "works on staging" to "first 20 paying customers in production" with monitoring, alerting, security verification, and store-distribution paths.

### Deliverables
1. **Security**
   - SPKI cert-pin rotation rehearsed end-to-end on staging (CRIT-08) — including the escape-hatch path.
   - JWT key rotation rehearsed (CRIT-04).
   - Mobile jailbreak/root detection (P2 from v1.0 spec; nice-to-have for soft launch).
   - WAF (Cloudflare or ModSecurity) in front of API.
2. **Observability**
   - Prometheus + Grafana dashboards for: open success rate, p50/p95 latency, payment success/failure, queue depth, Redis health, gateway health.
   - Alertmanager wired to Telegram + email per audit recommendations.
3. **Reliability**
   - Backup-restore drill: nightly DB backup restored to a clean VM; RTO measured.
   - Chaos pass: kill API node, kill worker, kill Redis primary, kill one gateway — observe correct degradation.
4. **Reminder + expiry cron** verified across daylight-saving boundary tests.
5. **Diagnostics**: scheduled ping job running; offline detection alerting.
6. **Load test**: 50 RPS sustained for 5 min, 200 RPS burst for 10 s, against staging. Capture report.
7. **Pen-test executed** (external vendor; booked at Phase-1 start). Findings triaged: critical/high fixed before launch, medium tracked.
8. **App-store submissions**: Play Internal Testing track, TestFlight, then production tracks.
9. **Soft launch**: first 20 customer devices live in production with explicit monitoring.

### Dependencies
- Phase 2 complete.
- Pen-test vendor available.
- Production infrastructure provisioned (final sizing per `TECHNICAL_SPECIFICATION.md` §18.3).
- Live Kapital production credentials.
- Production GSM gateway pair in production-grade facilities.

### Acceptance Criteria
- SLO dashboard shows ≥ 99.5 % availability across a 7-day soak.
- Open success rate ≥ 99 % on production traffic.
- p95 latency at or below targets per §3.1 v1.1.
- Pen-test report: zero open Critical, zero open High.
- App passes both store reviews.
- ≥ 20 production devices live; daily revenue dashboard non-zero; refund flow exercised at least once.

### Estimated Effort
**24 engineering days** over 6 weeks. Calendar is longer because pen-test, store reviews, and production deploys all wait on external clocks.

### Claude Opus Workflow
- **W-7 (runbooks)**: complete the production runbook set — incident-response, on-call escalation, backup-restore, key-rotation, gateway-failure, Redis-failover.
- **W-3 (test from spec)**: load-test scripts generated against the §3.1 target.
- **W-8 (adversarial)**: a multi-agent `Workflow` running 5 attack-scenario subagents against the deployed staging — auth bypass, payment manipulation, callback forgery, IDOR (HIGH-16-style), enumeration.
- **W-2 (plan-mode)** for the launch checklist: every "MUST be done before launch" item across all five v1.1 docs, cross-referenced and ticked.
- **Pen-test triage**: feed each finding to Opus for a proposed fix + risk classification; dev decides.
- **Soft-launch monitoring**: a `/loop` skill set to a 1-hour cadence during the first week post-launch, summarising metrics and surfacing anomalies.

---

## 7. Phase 4 — Scale & Polish (open-ended, post-launch)

Phase 4 is not a single chunk; it's a backlog of P2/P3 items to be sequenced after live traffic clarifies priorities. Listed here for completeness, not committed to a single timeline.

### Backlog Items (in expected priority order)

| Item | Source | Size | Notes |
|---|---|---|---|
| Auto-renew with Kapital tokenization | Tech spec §13.8 | M | Triggered by Kapital availability |
| Weekly whitelist drift audit | HIGH-02 / v1.1 Phase 2 | S | Earliest after 30 d of production drift data |
| SIM credit collectors | HIGH-04 / v1.1 Phase 2 | M | Per-operator implementation |
| Partial-refund admin UI | HIGH-08 / v1.1 Phase 2 | S | Algorithm exists; just UI |
| Email receipts | Tech spec §14.7 | S | P2 from v1.0 |
| PDF invoices | Tech spec §14.7 | M | P2 from v1.0; ties to AZ tax-receipt compliance |
| **OpenAPI v1.2 cutover** | Localization Spec Appendix D | M | Coordinated backend + mobile + admin release |
| Per-user access windows | Tech spec FR-OWN-03 | M | P2 |
| MQTT driver groundwork | Tech spec §12.9 | L | Triggered by MQTT-capable hardware availability |
| Reporting deepening (cohort/churn analytics) | Spec §19 Phase 4 | M | |
| Mobile UX iteration | Usage data | Ongoing | |
| Pseudolocalization in CI | Localization Spec §8.4 | S | When keyset exceeds ~3,000 entries |
| TMS integration (Crowdin/Lokalise) | Localization Spec §8.3 | M | When translator pool exceeds 2 people |
| Multi-tenant retrofit | HIGH-17 / `futures/multi-tenancy-retrofit.md` | L | Only if a white-label opportunity materialises |

### Phase 4 Operating Mode

Phase 4 doesn't have a "goal" in the Phase-0–3 sense. The operating mode shifts to **steady-state product engineering**:

- 30 % of time on planned backlog items
- 40 % on customer-driven feature work
- 20 % on bug fixes and observability tuning
- 10 % on tech-debt and refactoring

### Claude Opus Workflow in Phase 4

The Opus pattern shifts from "scaffold-heavy" to "review-and-iterate":

- **`/code-review`** at `high` effort on every PR (no longer just at chunk boundaries).
- **`/simplify`** runs monthly against the codebase as a cleanup pass.
- **`/security-review`** quarterly.
- **`Workflow` (multi-agent)** triggered for any feature touching three or more domains.
- **Plan-mode reviews** continue to be required for: anything touching payments, anything touching auth, anything touching cert/key rotation, anything cross-region/multi-tenant.

---

## 8. Critical Path

The single longest dependency chain (cannot be parallelised, even with AI assist):

```
Phase 0 procurement (3 wk)
  ↓ second GSM gateway delivery
Phase 1A-D backend (12 wk)
  ↓ a real device opens via API
Phase 2A-C mobile (9 wk)
  ↓ end-to-end UX
Phase 3 hardening + pen-test + store review (6 wk)
  ↓ first 20 paying customers
```

**Total critical path: 30 weeks ≈ 7 months.**

Items that **could** be parallelized if the team grows: Phase 1A (Identity) and Phase 1B (DeviceComm) could split; Phase 2A (mobile) could overlap with Phase 1D (admin shell) if a second engineer joins. None of this happens with one developer.

---

## 9. Risk Register

| Risk | Likelihood | Impact | Mitigation | Phase to address |
|---|---|---|---|---|
| CLIP fails on one or more AZ operators | M | H | Phase-0 gate G1; per-operator fallback to SMS in `OperatorFallbackPolicy` | Phase 0 |
| Kapital sandbox quirks surface in production | M | H | `getOrderStatus` cross-check + hourly reconciler + CI smoke against sandbox | Phase 1C |
| GSM gateway procurement delays | M | M | Order at Phase-0 start; second gateway optional for Phase-1 exit but mandatory for soft launch | Phase 0 |
| App-store rejection on first submission | M | M | Submit early in Phase 3, allow time for one resubmission | Phase 3 |
| Pen-test finds Critical issue late | L | H | Pen-test vendor booked at Phase-1 start, runs in Phase 3 → time for one round of fixes | Phase 3 |
| Solo dev burnout | M | H | Each phase has buffer; AI assistance reduces 2 AM debugging; runbooks reduce on-call load post-launch | Ongoing |
| Translation pipeline stalls (no translator) | M | M | Opus drafts adequate for QA; reviewer engagement deferred to a monthly batch | All |
| Production deploy on a Friday before incident | M | M | Operational policy: no production deploys after Wednesday noon | Phase 3+ |
| `device_users` STORED uniqueness fails CI concurrency test | L | M | Fallback Plan B (mirror table) documented in `DATABASE_ARCHITECTURE.md` §3.2 (HIGH-07) | Phase 1B |
| Kapital tokenization never arrives | L | M | Manual renewal path is the MVP design; auto-renew is additive | Ongoing |

---

## 10. Decision Gates (Go / No-Go)

The roadmap has four explicit gates where Opus-assisted dev cannot proceed without an external sign-off.

| Gate | When | Sign-off from | Without it… |
|---|---|---|---|
| **G-α** Phase-0 complete | End of week 3 | Tech lead + product | Don't start Phase 1 |
| **G-β** Phase-1 exit demo | End of week 15 | Product + DevOps | Don't start Phase 2 (mobile) |
| **G-γ** Pre-launch readiness | End of week 28 | Product + counsel + DevOps | Don't submit to stores |
| **G-δ** Soft-launch graduation | End of week 30, after 2 weeks of stable production | Product | Don't ramp to full launch |

Each gate's checklist is generated by Opus from the relevant spec sections at the start of the preceding phase; the dev ticks items as they land. Missing tick = no gate.

---

## 11. Summary Cards

### What the dev does this week (Phase 1A, week 1 example)

| Day | Activity | Opus pattern |
|---|---|---|
| Mon | Project scaffold, CI green | W-1, W-4 |
| Tue | Identity migrations + models | W-1, W-3 |
| Wed | OTP service + tests | W-3 |
| Thu | JWT issuance with `kid` + JWKS | W-2 (plan mode) |
| Fri | Recovery codes + tests; chunk review | W-3, W-8 |

### What the dev offloads to Opus continuously

- All boilerplate (models, migrations, requests, resources, policies)
- First-pass tests from spec tables
- Translations EN + RU from AZ source
- Runbook drafts
- PR review (`/code-review high` before merge)
- Spec ↔ code drift checks (W-5)

### What the dev MUST do themselves

- Real-hardware testing (GSM devices, real SIMs)
- Pen-test triage decisions
- Production deploys
- Talking to Kapital / carriers / counsel
- Final security review of payment + auth paths
- Customer support during soft launch

---

## 12. End-State After Phase 3 (Week 30)

- **Code:** v1.1 spec realised; ~80 % feature coverage of MVP.
- **Operations:** dual GSM gateway + Redis Sentinel + dual Reverb + DB primary/replica all running.
- **Compliance:** consent flow, anonymisation, audit immutability all live.
- **Customers:** 20+ paying devices, daily revenue, refund flow exercised.
- **Documentation:** v1.1 docs + complete runbook set + decision records + Phase-0 artefacts all in `docs/`.
- **Backlog:** Phase 4 list groomed, prioritised by usage data from the soft launch.

The product is launchable. Phase 4 is what makes it sustainable.

---

*End of Implementation Roadmap v1.0.*
