# Salam Həyətimiz — Production Architecture Report

**Document type:** Flagship system architecture reference
**Platform version:** v1.2 (UMKa / Traccar transport amendment)
**Report date:** 2026-06-21
**Status of the running system:** Live over HTTPS — application + Traccar operational on a single VPS (`185.208.206.174`, `salamheyetimiz.com`).
**Implementation stage:** Backend foundations + batches 00–09B complete (**225 tests passing, 743 assertions**); mobile app and the bulk of the admin panel are planned/in-progress.

> **Authority & accuracy note.** This report is synthesised strictly from the frozen corpus
> (`_api_ground_truth.md`, `DEPLOYMENT.md`, `PROJECT_CONSTITUTION.md`, `BACKEND_ARCHITECTURE.md`,
> `DATABASE_ARCHITECTURE.md`, `BATCH_09B_REVIEW.md`, `FINAL_PHASE0_VERDICT.md`, `SERVER_SIZING_GUIDE.md`)
> and the on-disk `app/Domain/` tree. Where a capability exists only in the OpenAPI contract but is **not**
> in the deployed `route:list`, it is explicitly marked *planned*. Endpoints and behaviours not supported by
> a source are not asserted.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Context Diagram](#2-system-context-diagram)
3. [Backend Architecture](#3-backend-architecture)
4. [Data Architecture](#4-data-architecture)
5. [Device Transport (v1.2)](#5-device-transport-v12)
6. [Open-Command Lifecycle State Machine](#6-open-command-lifecycle-state-machine)
7. [Authentication & Security](#7-authentication--security)
8. [Deployment Architecture (Production)](#8-deployment-architecture-production)
9. [Key Request Flows](#9-key-request-flows)
10. [Scalability & Extraction Path](#10-scalability--extraction-path)
11. [Current Implementation Status](#11-current-implementation-status)
12. [Risks & Open Items](#12-risks--open-items)

---

## 1. Executive Summary

**Salam Həyətimiz** ("Our Yard") is a **GSM/Traccar-based barrier/gate access-control platform for
residential complexes**. Residents open the entrance barrier or gate of their complex from a phone, the
complex's devices are managed remotely by operators, and access is gated by a per-(user, device)
subscription that the resident pays for through a bank-hosted payment page.

The platform is composed of five cooperating parts:

| Part | What it is | Status |
|---|---|---|
| **Mobile app** | Flutter app for residents/owners — OTP login, list devices, open a gate (biometric-gated), buy/renew subscriptions, manage their roster. | Planned (Phase 2A–2C). |
| **Admin panel** | Laravel Blade + Bootstrap 5 panel for operators/technicians/super-admins — device fleet, orders, refunds, diagnostics. | Partially deployed (auth + device + order/refund/subscription endpoints live). |
| **Backend** | Laravel 12 modular monolith (PHP 8.4) — the single authority for permissions, pricing, state transitions, and command dispatch. | Live; foundations + batches 00–09B implemented. |
| **Traccar** | Self-hosted telematics server (v6.14.5, Docker) — speaks the device's GPRS protocol, delivers the open command, and forwards telemetry back. | Live (operational; on-device framing pending one hardware test). |
| **UMKa 310 devices** | GLONASSSoft **UMKa 310 v2L** telematics trackers wired to the barrier relay — receive `OUTPUT0=1` and pulse the gate. | Hardware confirmed; on-device actuation test (HB1/T1) pending. |

**Defining architectural facts:**

- **The server is the authority; the client is advisory.** Every permission, price, and state transition is decided server-side at request time (`R-DOM-05`, `R-SEC-11`). The open-permission check is a single optimised SQL run on the primary DB on every tap — deliberately **not cached** (`HIGH-06`).
- **Honesty over optimism.** An open is reported as `dispatched` ("Göndərildi") until actuation is actually observed; only Traccar output read-back (or a future BLE script ack) promotes it to `opened` ("Açıldı") — driven by the `driver_confirms_actuation` flag (`R-GSM-03`, `CRIT-06` resolved).
- **A modular monolith bounded by domain.** Thirteen `app/Domain/<Module>` bounded contexts communicate only through their public service classes, domain events, and read models (`R-ARCH-02`, `R-ARCH-06`).
- **Idempotency everywhere externally invokable.** Opens, order creation, renewals, refunds, and bank callbacks are all replay-safe (`R-API-04`, `CRIT-07`).
- **The transport pivot (v1.2).** The original design assumed a CLIP GSM relay over a voice gateway with an on-device caller-ID whitelist. Confirmed field hardware (the UMKa 310 v2L telematics tracker) triggered a pivot to **Traccar as the single primary transport**, **SMS as emergency fallback**, and **BLE deferred** off the MVP critical path. The `DeviceDriver` seam and open-command control plane are unchanged; only the concrete drivers changed.

---

## 2. System Context Diagram

Actors and external systems surrounding the platform:

```
                                  ┌──────────────────────────────────────┐
   Residents / Owners             │            Cloudflare                │
   (Flutter mobile app)  ───────► │  Full(Strict) TLS · Always HTTPS     │
                                  │  WAF · Bot Fight · auth rate-limit    │
   Operators / Technicians /      │                                      │
   Super-admins  ───────────────► │  (proxied: salamheyetimiz.com)       │
   (Blade admin panel)            └───────────────────┬──────────────────┘
                                                      │ 443 (origin reachable
                                                      │      via Cloudflare IPs only)
                                                      ▼
   ┌──────────────────────────── VPS 185.208.206.174 ─────────────────────────────────┐
   │                                                                                   │
   │   Nginx ─► PHP-FPM (Laravel 12 modular monolith)  ◄──Horizon──► Redis 7           │
   │       │                          │                                                │
   │       │  webhooks                │ REST command / telemetry ingest                │
   │       │                          ▼                                                │
   │       │                  Traccar 6.14.5 (Docker)  ── Wialon IPS :5011 (public) ───┼──► UMKa 310 v2L
   │       │                          │  position-forward ─► /v1/traccar/forward        │    devices (GPRS)
   │       ▼                          ▼                                                │    barrier / gate relay
   │   MariaDB 11.4 (app)        traccar-db (Docker MariaDB, separate)                 │
   └─────────┬──────────────────────────────────┬──────────────────────┬──────────────┘
             │ HMAC callback                     │ SMS fallback open     │ OTP / invitation SMS
             ▼                                   ▼                       ▼
     ┌───────────────┐                   ┌───────────────┐       ┌───────────────┐
     │  Kapital Bank │                   │  SMS provider │       │  SMS provider │
     │  e-Commerce   │                   │  (open        │       │  (AZ vendor,  │
     │  3-D Secure   │                   │   fallback)   │       │   OTP/invite) │
     └───────────────┘                   └───────────────┘       └───────────────┘
```

| Actor / system | Role | Channel into the platform |
|---|---|---|
| **Residents / owners (mobile)** | End users — open gates, buy/renew subs, manage roster | HTTPS `/v1` via Cloudflare; bearer JWT |
| **Operators / technicians / super-admins** | Manage fleet, orders, refunds, diagnostics | HTTPS `/admin/v1` via Cloudflare; bearer JWT + 2FA |
| **UMKa 310 v2L devices** | Receive open command, pulse relay, emit telemetry | Wialon IPS raw TCP `:5011` (direct to Traccar; **not** via Cloudflare) |
| **Kapital Bank** | Hosted 3-D Secure payment + refund + status | Outbound REST from backend; inbound HMAC-signed callback webhook |
| **SMS provider** | OTP & invitation SMS, plus emergency open-command fallback | Outbound HTTP from backend; signed inbound webhook (fallback open correlation) |
| **Cloudflare** | TLS termination, WAF, rate-limit, origin shield | Reverse proxy in front of `:443` |

---

## 3. Backend Architecture

### 3.1 Stack & shape (`R-ARCH-01`, `R-ARCH-02`)

A **modular monolith** on **Laravel 12 / PHP 8.4 / MariaDB 11 / Redis 7 / Horizon / Reverb**, organised by
**bounded context** under `app/Domain/<Module>/`, never by Laravel artifact type. The codebase leans on
Laravel's strengths (Eloquent, Horizon, the container) and adds indirection only where it pays for itself —
at integration boundaries, on hot paths needing testability, and at security boundaries.

**Request path is fixed (`R-ARCH-04`):**

```
Form Request (validation)  →  thin Controller  →  Domain Service  →  Model / Adapter  →  API Resource
```

Controllers are thin (a Larastan rule flags methods over ~25 lines); they never touch Eloquent directly or
hold business logic. **Database transactions are opened only inside Services** (`R-ARCH-05`).

### 3.2 The 13 bounded contexts (confirmed on disk under `app/Domain/`)

| Module | Responsibility | Key public services |
|---|---|---|
| **Auth** | OTP issuance/verify, JWT issuance, refresh rotation, biometrics, admin 2FA | `OtpService`, `UserAuthenticator`, `AdminAuthenticator`, `BiometricService` |
| **Users** | Mobile profile, language, soft-delete lifecycle | `UserProfileService`, `UserFinder` |
| **Catalog** | Reference data: SIM operators, device models, regions | `CatalogQuery` |
| **Devices** | Device lifecycle, assignment, state machine, the open-permission query | `DeviceRegistrar`, `DeviceAssigner`, `DeviceStateMachine`, `DeviceAccessQuery` |
| **Roster** | Who can use a device + invitation flow; whitelist outbox triggers | `RosterService`, `InvitationService` |
| **DeviceComm** | The `DeviceDriver` layer — open-command pipeline, Traccar/SMS transports, diagnostics, whitelist outbox | `OpenCommandService`, `WhitelistService`, `DiagnosticsService`, `DriverResolver` |
| **Subscriptions** | Per-(user, device) entitlement lifecycle, renewal, expiry | `SubscriptionService`, `RenewalService`, `ExpirySweep` |
| **Payments** | Orders, payment events, refunds, Kapital integration | `OrderService`, `PaymentCallbackService`, `PaymentVerifierService`, `RefundService`, `OrderReconciler` |
| **Notifications** | Template-driven, multi-channel, idempotent fan-out | `NotificationDispatcher`, `TemplateRenderer` |
| **Audit** | Immutable, queryable log of every privileged action | `Audit` facade, `AuditSearchQuery` |
| **Privacy** | Consents, data-subject requests (export/delete), anonymisation | `ConsentService`, `DataSubjectRequestService`, `AnonymizationService` |
| **Reporting** | Materialised daily stats + async report jobs | `DailyStatsBuilder`, `ReportJobService` |
| **Admin** | Admin users, settings, feature flags, notification-template management | `AdminUserService`, `SettingService`, `FeatureFlagService` |

Every module follows the same internal shape (`Models/`, `Services/`, `Actions/`, `Queries/`, `Adapters/`,
`DTOs/`, `Events/`, `Listeners/`, `Jobs/`, `Policies/`, `Rules/`, `Exceptions/`, `Support/`, and a
`ModuleServiceProvider.php`). The DeviceComm and Payments folders on disk confirm this layout.

### 3.3 How modules communicate (`R-ARCH-06`, `R-ARCH-07`)

A module may depend on another **only** through:

1. **Public service classes** in the other module's `Services/` namespace;
2. **Domain events** dispatched on Laravel's event bus (past-tense, no `Event` suffix);
3. **Read models** (Eloquent models scoped to read use cases).

A module **MUST NOT** reach into another module's models, jobs, or migrations. **Cross-cutting concerns
(`Audit`, `Notifications`) react to events** — feature code never calls them directly. New modules are
auto-discovered: `DomainServiceProvider` iterates `app/Domain/*/` and registers each `ModuleServiceProvider`,
so adding a module never touches `config/app.php`.

Representative event flow (the spine of the system):

```
OpenCommandRequested ─► DeviceComm dispatches  ─► OpenCommandDispatched ─► (awaits telemetry)
                                                └► OpenCommandCompleted / Failed ─► Audit + Notifications + Reverb
OrderPaid ─► Subscriptions::activate ─► SubscriptionActivated ─► Roster whitelist-add + Notifications (receipt)
DeviceUserAdded ─► DeviceComm whitelist-add (outbox) + Audit + Notifications
```

### 3.4 The `DeviceDriver` seam (`R-GSM-01`)

The single most important extensibility boundary. All device communication goes through the
`DeviceDriver` interface — `open`, `whitelistAdd`, `whitelistRemove`, `diagnose`, `supports` — resolved
per device by `DriverResolver::for(Device)` from `device.driver_type`. Adding a protocol must **not**
require core changes. Concrete drivers (built / reserved):

| Driver | Role | Status |
|---|---|---|
| `TraccarDriver` (`device-driver.traccar`) | Primary — remote + in-person; `open()` → `OUTPUT0=1` custom command | Implemented (09-B) |
| `SmsDriver` (`device-driver.sms`) | Emergency fallback when device is offline from Traccar | Implemented (09-B) |
| `BleProvisioningDriver` (`device-driver.ble`) | Reserved slot; **deferred** off the MVP critical path | Not implemented |

The retired CLIP driver, the `VoiceGateway`/`VoiceGatewaySelector`, and `OperatorFallbackPolicy` were
removed in the pivot (`CRIT-01` and `CRIT-03` retired). Every external dependency (Traccar, SMS, Kapital,
FCM, object storage) sits behind an interface bound in `IntegrationsServiceProvider` with a `Fake*` test
double (`R-ARCH-08`).

### 3.5 Async work — Horizon queues (`R-WF-08`)

Seven named queues, each a dedicated supervisor group: `high` (open-command dispatch, payment-callback
processing), `default`, `device-comm` (Traccar command, whitelist/credential sync, diagnostics, SMS fallback —
`WithoutOverlapping` per device), `notifications`, `payments`, `reports`, `privacy`. Scheduled commands all
declare `withoutOverlapping()` + `onOneServer()`; human-time crons run in `Asia/Baku`.

---

## 4. Data Architecture

### 4.1 MariaDB schema highlights

Single MariaDB 11.4 app database (`salam`), InnoDB, `utf8mb4_unicode_ci`. Conventions are locked
(`R-DOM-15/16/17`): **money is integer minor units (qəpik)**, **timestamps are UTC** (hot event tables use
`TIMESTAMP(3)`), soft-delete is used **only** on `users` and `devices`; everything else uses status enums.

Schema is built in 12 ordered migration batches (`00_system` → `11_post_seed_constraints`), each batch
referencing only prior-batch tables. Key entity groups:

| Group (batch) | Core tables |
|---|---|
| Identity & auth (02–03) | `users`, `admin_users`, `user_devices`, `refresh_tokens`, `otps`, `auth_attempts`, `user_consents` |
| Devices & roster (04) | `devices`, `device_users` (with STORED generated `is_active`), `device_user_history`, `invitations` |
| Payments (05) | `card_tokens`, `orders`, `order_items`, `payments`, `payment_logs`*, `payment_callbacks`, `refunds` |
| Subscriptions (06) | `subscriptions`, `subscription_periods` |
| Device ops (07) | `open_commands`*, `device_diagnostics`*, `whitelist_changes`, `traccar_devices`, `open_command_attempts`, `open_command_feedback` |
| Notifications (08) | `notification_templates`, `notification_template_locales`, `notifications`*, `user_notification_settings` |
| Audit / ops (09) | `audit_log`*, `settings`, `feature_flags`, `idempotency_keys`, `data_subject_requests`, `report_jobs` |
| Stats (10) | `device_daily_stats`, `revenue_daily_stats`, `subscription_daily_stats` |

`*` = partitioned (see below).

Notable invariants enforced in schema/app:
- **`device_users` active-uniqueness** `(device_id, user_id) where status='active'` via a STORED generated `is_active` column + UNIQUE (MariaDB lacks partial unique indexes); verified by the `ci:device-users-uniqueness` concurrency test (`R-DOM-10`).
- **Subscription** = one logical sub per `(user, device)` pair via `subscriptions.device_user_id` UNIQUE; renewals extend `ends_at` and append rows to `subscription_periods` (`R-DOM-03`).
- **`audit_log` / `payment_logs` are immutable** — the runtime DB role holds `INSERT, SELECT` only (no `UPDATE`/`DELETE`), enforced by committed GRANTs + the `ci:grants:audit-log-immutable` build check + a daily monitor (`R-SEC-12`).

### 4.2 Partitioned tables (RANGE by month, no FKs, app-enforced)

Tables expected to exceed 50 M rows in year 1 are `RANGE`-partitioned **from first deploy** (not retrofitted)
on a `created_at`-derived `YYYYMM` integer `partition_key`, with 12 forward partitions and a monthly cron
that rolls one forward (`R-DOM-18`, DB Arch §0.6).

| Table | Why partitioned | Retention | FK policy |
|---|---|---|---|
| `open_commands` | Highest write volume — one row per tap (~50/s sustained, 200/s burst target) | 24 months hot, then archive to S3 Parquet + DROP | App-enforced; PK `(id, partition_key)` |
| `audit_log` | Every privileged + financial action | 5 years (archive > 12 mo to cold storage) | Polymorphic `actor_id`, **no FK** (app-checked) |
| `payment_logs` | Every Kapital req/resp pair | 5 years | App-enforced |
| `device_diagnostics` | Telemetry pings × devices × sources | 12 months | **No FK** — partitioned raw-SQL table, `timestamps` off (09-B) |
| `notifications` | Reminder + open fan-out | 12 months (inapp channel) | App-enforced |

> Partitioned tables cannot carry conventional foreign keys because the partition key must be part of every
> unique key; referential integrity for these tables is **enforced at the application layer** and verified by
> periodic referential-check jobs and CI.

### 4.3 Redis logical-DB split (`R-ARCH-10`, `CRIT-05`)

Concerns are isolated onto separate Redis logical databases so memory pressure is bounded and each concern
can migrate out later:

| DB | Concern | `maxmemory-policy` |
|---|---|---|
| 0 | application cache | `allkeys-lru` |
| 1 | queue (Horizon) | `noeviction` |
| 2 | locks (cooldowns, whitelist drain, scheduler one-server) | `noeviction` |
| 3 | broadcasting (Reverb pub/sub) | `noeviction` |
| 4 | idempotency hot tier | `allkeys-lru` |

The **target topology is Redis HA (Sentinel: 1 primary + 2 replicas + 3 sentinels)** with a connection-level
circuit breaker that returns `503` for opens when Redis is unhealthy — it must never hang. *(In the current
single-VPS pilot, Redis runs as a single instance; Sentinel HA is the Tier-3+ target — see §10.)*

**Idempotency** is two-tier: Redis (hot) + the durable `idempotency_keys` table. The DB row is the source
of truth; Redis is the replay-speed cache. A replay with a mismatched body returns `409 idempotency_mismatch`
(`R-API-04`).

---

## 5. Device Transport (v1.2)

**Hardware:** GLONASSSoft **UMKa 310 v2L** — a telematics GPS tracker (Wialon IPS / Wialon Combine
protocols), wired so its digital output drives the barrier relay. It is **not** the originally-assumed CLIP
GSM relay; this difference is the entire reason for the v1.2 pivot.

### 5.1 Transports & roles

| Transport | Role | Confirms actuation? |
|---|---|---|
| **Traccar** (Wialon IPS, port `5011`) | **Primary** — both remote and in-person opens | **Yes** — output-state read-back / command ack |
| **SMS** | **Emergency fallback** only (device offline from Traccar) | No — terminates at `dispatched` |
| **BLE** | **Deferred** off the MVP critical path | n/a |

**Why BLE was demoted (`FINAL_PHASE0_VERDICT.md`):** the UMKa's BLE is **iBeacon UUID identification**, not
an authenticated phone→device command channel — it is unauthenticated (clonable/replayable) and
iOS-background-restricted. It cannot serve as a secure primary local-open, so it was abandoned as the local
primary. Removing the BLE-first split also **withdrew the R-DOM-05 time-boxed-entitlement exception**: with
no offline local open, **all opens (local and remote) use the single real-time server check** (active
subscription + on roster + device not disabled + cooldown not violated).

### 5.2 The open command

The UMKa exposes a documented native command — **`OUTPUT0=1`** (turn output on) / `OUTPUT0=0` (cmd #25,
fw 0.12.8+), deliverable over GPRS. Both in-person and remote opens converge on the same path:

```
app tap → backend (real-time auth) → Traccar custom command (OUTPUT0=1 / cmdout.p) → live Wialon session → relay pulse
```

`TraccarDriver::open()` sends Traccar a text **`custom`** command (`POST /api/commands/send`, `type: custom`,
`attributes.data = "OUTPUT0=1"`) over the device's live Wialon session, with Traccar offline-queueing. If the
device is offline from Traccar, the open falls to the **SMS** driver once; it must never queue indefinitely.

### 5.3 Actuation confirmation (`CRIT-06` resolved, `R-GSM-03`)

A terminal `opened` state is permitted **only** when actuation is observable. Traccar **forwards positions /
I-O state / command results** to the backend's ingestion webhook (`POST /v1/traccar/forward`, shared-secret
token). `TraccarIngestionService` reads the configured output attribute from the forwarded position; an
output-on reading within the confirmation window **upgrades a recent `dispatched` open to `opened`**. SMS
dispatches, having no read-back, stay at `dispatched`. The API surfaces `driver_confirms_actuation`; the UI
renders "Açıldı" only when `true`, else "Göndərildi".

### 5.4 Whitelist / authorisation model

Authorisation under Traccar is **platform-side** (server decides every open in real time), so
`whitelistAdd/Remove` are effectively **no-ops** that complete the outbox immediately as `synced`. The
`whitelist_changes` outbox is retained as the **audit channel** and the future BLE/credential-provisioning
path (`R-GSM-07`). The vestigial `open_command_attempts.voice_gateway_id` column survives (always `null`
under v1.2) to keep the 09-A control plane unchanged.

### 5.5 Telemetry & offline detection (`R-GSM-10`)

Telemetry and online/offline status arrive via **Traccar event-forward** (positions, I/O state, command
results, online status). A device with no telemetry for **> 24 h** is flagged offline (opens are still
attempted). Inbound SMS replies are HMAC-authenticated and correlated to an `open_commands` row **on the SMS
fallback path only**.

> **Field-validation caveat (`BATCH_09B_REVIEW.md` §7):** the seam, contract, and confirmation logic are
> implemented and tested against `FakeTraccarClient`, but the **exact Wialon framing the UMKa accepts**, the
> **output-bit attribute key/casing**, and the **online→`sent` / offline→`queued` status mapping** are
> confirmable only against a real device + the deployed Traccar (Phase-0 test **T1/HB1**). All are
> configurable without code change.

---

## 6. Open-Command Lifecycle State Machine

Every open writes one `open_commands` row — the most write-heavy table. States are exactly
`queued, dispatching, dispatched, opened, failed, expired` (`R-GSM-06`).

```
                         (open accepted, idempotency-keyed, 202)
                                      │
                                      ▼
                                ┌──────────┐
                                │  queued  │
                                └────┬─────┘
                                     │ DispatchOpenCommandJob (high queue) resolves driver
                                     ▼
                               ┌─────────────┐
                               │ dispatching │
                               └──────┬──────┘
                  driver acknowledged │ send
                          ┌───────────┼─────────────────────┐
                          ▼           ▼                     ▼
                   ┌────────────┐  ┌────────┐         ┌────────────┐
                   │ dispatched │  │ failed │         │  expired   │
                   │ (Göndərildi)│ └────────┘         │ (never     │
                   └─────┬──────┘   ▲    ▲            │  confirmed)│
   Traccar telemetry     │          │    │ transient  └────────────┘
   output-on read-back   │          │    │ failure → ONE fallback
   within window         ▼          │    │ (device_models.fallback_open_driver,
                   ┌──────────┐     │    │  typically SMS)
                   │  opened  │     │    └─────────── non-transient
                   │ (Açıldı) │     │                (device_disabled, driver_unsupported, cooldown
                   └──────────┘     │                 → no fallback)
                                    │
                       SMS path terminates at `dispatched` (no read-back)
```

Governing rules:

| Rule | Statement |
|---|---|
| `R-GSM-03` | `opened` is reached **only** when actuation is observable (Traccar read-back / command ack). Unconfirmed dispatch terminates at `dispatched`. |
| `R-GSM-04` | On a **transient** failure, retry **once** via `device_models.fallback_open_driver` (typically SMS). Both attempts persist as `open_command_attempts` rows; the parent `state` is the last attempt's terminal state. **Non-transient** failures (`device_disabled`, `driver_unsupported`, `cooldown`) never fall back. |
| `R-GSM-06` | The exact six-state set; every open writes an `open_commands` row. |
| `R-GSM-09` | `expected_completion_ms` is **server-computed** from a rolling p90 of recent successful opens per driver/device (per-driver constant when < 10 samples) — never a fixed constant. |

The open response returns `expected_completion_ms`, `driver_confirms_actuation`, and a `websocket_channel`
(`private-user.{id}`). Real-time push (Reverb) is **deferred**; the mobile client **must poll**
`GET /v1/commands/{commandId}` at ~1 s as the launch-critical fallback (`R-ARCH-12`). Cooldowns are enforced
per (user, device) and per device-global via Redis locks; an open hitting cooldown returns `429 cooldown`
with `Retry-After`.

---

## 7. Authentication & Security

### 7.1 Token model (`R-SEC-02`, `R-SEC-05`)

| Audience | Login | Access token | Refresh | Algorithm |
|---|---|---|---|---|
| **Mobile (`userBearerAuth`)** | Phone + 6-digit SMS OTP | **RS256 JWT, 15 min** (claims `sub`, `kind`, `fp`, `kid`) | Opaque, **60 days**, SHA-256 hashed, **rotated each use**, device-fingerprint bound | RS256 |
| **Admin (`adminBearerAuth`)** | Email + password (bcrypt cost 12) + TOTP 2FA | **RS256 JWT, 30 min** (claims `role`, `tfa_verified`, `kid`) | Opaque, 60 days, rotated | RS256 |

The two guards are **disjoint** and not interchangeable (`R-API-03`). The JWT library is `lcobucci/jwt`
(pinned). Access tokens can be revoked via a short-lived Redis `jti` denylist; refresh tokens are revoked in
the DB.

### 7.2 Mobile OTP flow

`POST /v1/auth/otp/request {phone}` → 202 (unknown phone auto-registers, `R-DOM-01`); then
`POST /v1/auth/otp/verify {phone, code, device:{install_uuid, platform}}` → `AuthSuccess`.
OTP rules (`R-SEC-03`): TTL 120 s; max 5 attempts/OTP; max 3 OTPs/phone/10 min; 30/IP/hour; codes stored
**hashed**, never logged. Refresh via `POST /v1/auth/refresh`; logout revokes the refresh family.
**Biometric unlock is required before every open command** (Face ID / Touch ID / Android BiometricPrompt),
app-enforced with PIN fallback (`R-SEC-04`).

### 7.3 Admin 2FA

`POST /admin/v1/auth/login {email, password}` returns either `AdminAuthSuccess` or, when 2FA is enabled, a
**challenge** (`{challenge_token, expires_in_seconds}`). `POST /admin/v1/auth/2fa/verify {challenge_token, code}`
accepts a TOTP **or** a single-use recovery code → `AdminAuthSuccess` (`tfa_verified=true`).
**Recovery codes are MVP** (`R-SEC-06`): 8 single-use codes, shown once, bcrypt-hashed, regenerated atomically.
State-mutating admin routes require `tfa_verified=true`. A production invariant requires **≥ 2 active
super-admins** (`R-SEC-07`).

### 7.4 Keys / JWKS (`R-SEC-08`, `CRIT-04`)

JWTs carry a `kid`; admin signing keys are published at `GET /.well-known/jwks.json` (**admin host only**).
Two `kid`s may be valid during rotation. **Mobile pins** two SPKI public-key hashes per host (rotated by app
release) and does **not** consume JWKS.

### 7.5 Idempotency, rate limiting, audit

- **Idempotency-Key is REQUIRED** on `openDevice`, `createOrder`, `renewSubscription`, and `adminRefundOrder` (`R-API-04`); accepted on all mutating endpoints; same key replays the same result.
- **Rate limits** (gateway, `R-SEC-16`): OTP request 3/phone/10 min + 30/IP/hour; OTP verify 10/phone/10 min; open 12/user/min + 4/device/min; authed mobile 120/min/user; admin 600/min/admin. 429 responses carry `rate_limited` / `cooldown` + `Retry-After`.
- **Error envelope** is fixed: `{ error: { code, message_key, message, details, request_id } }`; the API returns stable `code` + `message_key`, not a contractual translated string (`R-LOC-02`).
- **Audit** is append-only, immutable, 5-year retention; cross-cutting modules react to events rather than being called directly.
- **Payment data** never includes full PAN/CVV/3DS values; callbacks are verified over **raw request bytes** (HMAC-SHA256), deduped durably via `payment_callbacks.payload_hash`, and the bank's `getOrderStatus` is the authoritative state — the callback body is never trusted for the state decision (`R-PAY-01/03/04/06`).

---

## 8. Deployment Architecture (Production)

The platform currently runs as a **single co-located VPS** — Tier 1–2 of `SERVER_SIZING_GUIDE.md` — serving
the pilot (≤ 500 stationary devices). Native stack for the application; Traccar in Docker.

```
                ┌──────────── Cloudflare (proxy) ─────────────┐
   Mobile/Admin │  Full(Strict) TLS · Always HTTPS · WAF      │
   ───────────► │  Bot Fight · auth-endpoint rate-limit       │
                └───────────────────┬─────────────────────────┘
                                    │ 443 (UFW: Cloudflare IPs only)
   UMKa devices ─(Wialon IPS, raw TCP :5011, public)──────┐    (A record `gps` = DNS-only / direct IP)
                                    │                      │
   ┌──────────────── VPS 185.208.206.174  (Ubuntu 24.04.4 LTS · 6 vCPU · 11 GiB · 96 GB SSD) ─────────────┐
   │  Nginx 1.24 :80→:443 ──FastCGI──► PHP 8.4.22 FPM (Laravel, /var/www/salam/public)                    │
   │       │ (Cloudflare Origin cert /etc/ssl/cloudflare)      │                                          │
   │       │                                          Horizon (systemd salam-horizon, 7 queues)           │
   │       │                                          Scheduler (systemd timer, 1/min) ── Redis 7 :6379   │
   │       │                                                                              (localhost,auth)│
   │  Traccar 6.14.5 (Docker) ──REST──────────────────┘     MariaDB 11.4 :3306 (localhost) — DB: salam    │
   │     :8082 UI (localhost) · :5011 Wialon (public)                                                     │
   │     position-forward ─► http://host.docker.internal/v1/traccar/forward                               │
   │     traccar-db (Docker MariaDB) — DB: traccar (separate)                                             │
   │  fail2ban · UFW · swap 4G · nightly backup · logrotate                                               │
   └─────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 8.1 Components & versions

| Component | Version | How it runs |
|---|---|---|
| OS | Ubuntu 24.04.4 LTS (tz Asia/Baku, swap 4G) | — |
| Nginx | 1.24.0 | systemd `nginx` |
| PHP-FPM | 8.4.22 | systemd `php8.4-fpm` (pool www) |
| MariaDB (app) | 11.4.12 | systemd `mariadb` (bind 127.0.0.1) |
| Redis | 7.0.15 | systemd `redis-server` (`requirepass`, `noeviction`, AOF) |
| Horizon | laravel/horizon | systemd `salam-horizon` (7 supervisors) |
| Scheduler | `schedule:run` | systemd `salam-scheduler.timer` (1/min) |
| Traccar | 6.14.5 | Docker compose (`/opt/salam-traccar`) |
| Traccar DB | mariadb 11.4 | Docker (`traccar-db`, separate DB) |
| Docker | CE 29.6 | systemd `docker` |
| fail2ban | 1.0.2 | systemd (`sshd` jail) |

### 8.2 Resource allocation (single box, 11 GiB)

| Component | RAM cap | Key setting |
|---|---|---|
| MariaDB (app) | ~3.0 GiB | `innodb_buffer_pool_size=2560M`, `flush_log_at_trx_commit=1` |
| PHP-FPM | ~1.2 GiB | `pm=dynamic`, `pm.max_children=20` |
| Horizon | ~0.7 GiB idle (peak more) | `balance=auto` (scales under load) |
| Redis | ~0.5 GiB | `maxmemory 512mb`, `noeviction`, AOF |
| Traccar JVM | ~1.0 GiB | `-Xms512m -Xmx1024m`, `mem_limit 1536m` |
| traccar-db | ~0.4 GiB | `innodb-buffer-pool-size=256M` |
| OS + Nginx | ~1.0 GiB | swap 4G (swappiness 10) safety net |
| **Peak** | **~8 GiB / 11 GiB** | **~3 GiB headroom** |

### 8.3 Network / security posture (UFW)

| Port | Exposure | Purpose |
|---|---|---|
| 22/tcp | public (fail2ban) | SSH (root + password — hardening deferred) |
| 80/tcp | Cloudflare IPs + Docker bridge | HTTP→HTTPS redirect; internal Traccar webhook (172.16/12 only) |
| 443/tcp | **Cloudflare IPs only** | HTTPS origin (Cloudflare Origin cert) |
| 5011/tcp | **public** | Traccar Wialon IPS (devices connect directly) |
| 8082, 3306, 6379 | localhost only | Traccar UI, MariaDB, Redis |

UFW default **deny incoming / allow outgoing**, `DEFAULT_FORWARD_POLICY=ACCEPT` (Docker). Cloudflare IP
ranges are fetched live (22 rules).

### 8.4 Cloudflare

- **DNS:** A `salamheyetimiz.com` → `185.208.206.174` (proxied); CNAME `www` → apex (proxied); A `gps` → IP (**DNS-only**, so devices reach Traccar's `:5011` directly, not through the proxy).
- **SSL/TLS:** **Full (Strict)**; Cloudflare Origin CA cert (expires 2041) at `/etc/ssl/cloudflare/`; Always Use HTTPS on; Min TLS 1.2.
- **Caching:** `/v1/*` and `/admin/*` → **Bypass** (dynamic API).
- **Security:** Bot Fight Mode, WAF Managed Ruleset, auth-endpoint rate-limit; origin reachable only via Cloudflare (enforced by UFW).

### 8.5 Verified state (2026-06-21)

`/v1/health/live` → 200; `/v1/health/ready` → 200 `{"database":true,"redis":true}`; HTTP→HTTPS 301;
Full(Strict) validated; migrations + seed applied (3 SIM operators, 1 device model = UMKa 310 v2L, 9 regions);
Horizon up (7 supervisors); scheduler firing every minute; nightly backup verified; Traccar + traccar-db
healthy; Wialon `:5011` listening; Traccar API token wired.

---

## 9. Key Request Flows

### 9.1 Mobile OTP login

```
Mobile app          Backend (/v1)           OtpService / Auth        SMS provider
   │ POST /auth/otp/request {phone}  │              │                     │
   ├────────────────►│ validate, rate-limit          │                     │
   │                 ├──────────────►│ create hashed OTP row               │
   │                 │               ├─ OtpRequested ─► SendOtpSmsJob ─────►│ (sends 6-digit code)
   │   202 Accepted  │◄──────────────┤                                     │
   │◄────────────────┤                                                     │
   │ POST /auth/otp/verify {phone, code, device}     │                     │
   ├────────────────►│ verify hash, attempts ≤ 5     │                     │
   │                 ├──────────────►│ OtpVerified; UserAuthenticator      │
   │                 │               │  issues RS256 access (15m, kid,fp)  │
   │                 │               │  + opaque refresh (60d, hashed)     │
   │  200 AuthSuccess│◄──────────────┤                                     │
   │◄────────────────┤  {access_token, refresh_token, user}               │
```

### 9.2 Open a gate (Traccar primary, with polling fallback)

```
Mobile        Backend (/v1)         DeviceAccessQuery   OpenCommandService   Traccar       UMKa
  │ biometric prompt (app-enforced) │                       │                  │            │
  │ POST /devices/{id}/open          │  (Idempotency-Key required)             │            │
  ├────────────►│ idempotency + cooldown (Redis locks)      │                  │            │
  │             ├──────────►│ canOpen? (single SQL on primary: sub active      │            │
  │             │           │  + on roster + not disabled)  │                  │            │
  │             │◄──────────┤ allowed                       │                  │            │
  │             ├──────────────────────►│ write open_commands(queued)          │            │
  │  202 {commandId,         │           │  OpenCommandRequested               │            │
  │   expected_completion_ms,│          DispatchOpenCommandJob (high)          │            │
  │   driver_confirms_actuation:true,    │ resolve TraccarDriver               │            │
  │   websocket_channel}     │           ├─ OUTPUT0=1 (custom cmd) ───────────►│──GPRS─────►│ relay pulse
  │◄────────────┤            │           │  state → dispatched                 │            │
  │                                                                            │            │
  │  ── poll every ~1s (Reverb deferred) ──                                    │            │
  │ GET /commands/{id}  ────►│ state: dispatched ("Göndərildi")                │            │
  │◄────────────┤            │                  Traccar event-forward ◄────────┤ output-on  │
  │             │  POST /v1/traccar/forward (shared token) → ingestion         │ telemetry  │
  │             │  output-on within window ⇒ dispatched → opened              │            │
  │ GET /commands/{id}  ────►│ state: opened ("Açıldı")                        │            │
  │◄────────────┤            │                                                 │            │
  │  (if device offline from Traccar: ONE fallback to SmsDriver → dispatched, no read-back) │
```

### 9.3 Buy / renew subscription via Kapital

```
Mobile        Backend (/v1)        OrderService       Kapital Bank       Subscriptions
  │ POST /subscriptions/{id}/renew (Idempotency-Key)  │                      │
  │   — or — POST /orders                              │                      │
  ├────────────►│ create order (pending) ────►│ registerOrder ──────────────►│ (hosted 3DS page)
  │  200/redirect {bank_redirect_url}          │  order → authorising         │
  │◄────────────┤                              │                              │
  │ ── user completes 3-D Secure on bank's hosted page ──                     │
  │                                            Kapital ─ HMAC callback ──►│ /v1/payments/callback
  │                                            (raw-body HMAC verify; PENDING never mutates order)
  │                                            ProcessPaymentCallbackJob ─► getOrderStatus (authoritative)
  │                                            order → paid ⇒ OrderPaid ─────►│ Subscriptions::activate
  │                                                                           │  SubscriptionActivated
  │                                                                           │  ⇒ whitelist-add + receipt
  │ GET /orders/{id} (return-URL orderId is a hint; OrderPolicy verifies payer)│
  │◄── order paid, subscription active ─────────────────────────────────────  │
  │  (POST /orders/{id}/recheck reconciles vs bank on demand; hourly OrderReconciler sweeps stuck orders)
```

### 9.4 Traccar telemetry forward → actuation confirmation

```
UMKa device         Traccar (:5011)        Backend /v1/traccar/forward    OpenCommandService
   │ position / I-O state (Wialon IPS) ────►│                                  │
   │                                        ├─ event-forward POST ────────────►│ (shared-secret token gate)
   │                                        │   {device, attributes:{output…}} │
   │                                        │                 TraccarIngestionService:
   │                                        │                  ├─ record DeviceDiagnostic (partitioned)
   │                                        │                  ├─ update online status (>24h ⇒ offline)
   │                                        │                  └─ output-on within confirm window?
   │                                        │                       ⇒ confirmActuation():
   │                                        │                          recent dispatched open → opened
   │                                        │   200 OK                          │  OpenCommandCompleted
   │                                        │◄─────────────────────────────────┤  ⇒ Audit + (opt) push
```

---

## 10. Scalability & Extraction Path

The system co-locates everything on one box today; the documented growth path splits components out by
device-count tier (`SERVER_SIZING_GUIDE.md`). Sizing assumes **stationary, mains-powered barriers** with low,
infrequent telemetry (~1 msg/min/device static, plus bursty-but-low-volume opens).

| Tier | Devices | Topology | What gets split out |
|---|---|---|---|
| **Tier 1** | 100 | Single VM (2–4 vCPU / 8 GB), everything co-located (containers with limits) | Nothing — vertical only |
| **Tier 2** | 500 | 1–2 VMs (~12–16 GB total); prefer separating Traccar | **Traccar → its own VM** next |
| **Tier 3** | 1,000 | App VM + **MariaDB primary + read replica** + **Redis Sentinel HA** + **dedicated Traccar VM** | **DB replica**, **Redis HA**, **Traccar dedicated (required)** |
| **Tier 4** | 5,000 | 2× app nodes behind LB, DB primary+replica (bare-metal/managed), Redis HA, 2× Reverb (sticky LB), dedicated Traccar | Backend goes **horizontal**; Reverb scales out |

**The current production box (6 vCPU / 11 GiB) straddles Tier 1–2** and is sized for the pilot (≤ 500
devices). Per-component scale drivers and triggers:

| Component | Scale path | Split trigger |
|---|---|---|
| **Traccar** | Vertical (JVM heap 1–4 GB) to 5k; shard only past ~10k | **Separate VM at Tier 2; dedicated host required at Tier 3+** |
| **MariaDB** | Vertical + read replica; buffer pool ≈ 70% RAM; partition pruning + 24-mo retention keep the hot set bounded | Add **read replica at Tier 3**; the open-permission check and payment-callback processing stay on **primary only** (`R-ARCH-11`) |
| **Backend (PHP-FPM + Horizon)** | **Horizontal** behind LB | Add app nodes at Tier 4 |
| **Redis** | Single now → **Sentinel HA (1 primary + 2 replicas + 3 sentinels)** | HA at Tier 3 (`R-ARCH-10`) |
| **Reverb** | 2+ nodes behind a sticky LB | Tier 4 (WebSocket is an optimisation; polling is the launch-critical fallback, `R-ARCH-12`) |

The architecture's most likely **first extraction** is the **DeviceComm driver layer** — a separate process
running closer to the GSM/Traccar hardware (Backend Arch §16). The `DeviceDriver` seam and the
`Broadcasting/` boundary are deliberately kept thin to make these splits mechanical rather than structural.
**Traccar always uses a separate database from the application** at every tier (co-located only ≤ 500 devices).

---

## 11. Current Implementation Status

**Foundations + batches 00–09B are implemented and verified — 225 tests passing (743 assertions); OpenAPI
structurally valid.** The deployed `route:list` is the ground truth for "what is live"; the OpenAPI spec
(100 operations) describes the full contract, much of which is **planned, not built**.

### 11.1 Implemented (live in production `route:list`)

| Area | Implemented endpoints / capability |
|---|---|
| **Mobile auth** | `otp/request`, `otp/verify`, `refresh`, `logout`; biometrics enroll/disable |
| **Devices (mobile)** | list (`?filter`), get, stats, **open** (idempotency-keyed), list device commands, get command, submit open feedback |
| **Subscriptions (mobile)** | list, get, renew, toggle auto-renew |
| **Orders (mobile)** | list, create, get, recheck |
| **Technical (mobile host, admin JWT)** | register device, assign device |
| **Webhooks** | `payments/callback` (Kapital HMAC), **`traccar/forward`** (shared token) |
| **Health** | `health/live`, `health/ready` |
| **Admin auth** | login, 2FA verify, logout, me, regenerate recovery codes |
| **Admin devices** | list, create, get (with roster), update, decommission, disable/enable, transfer, commands, **diagnostics** (09-B), whitelist-queue, whitelist resync |
| **Admin payments** | list/get/recheck/refund orders, list refunds, list subscriptions |
| **DeviceComm transport (09-B)** | `TraccarDriver` (`OUTPUT0=1`), `SmsDriver` fallback, Traccar event-forward ingestion (diagnostics / online / actuation confirm), Traccar device mapping, `device_diagnostics` + `traccar_devices` tables, whitelist outbox drain (`WhitelistSyncJob`) |

### 11.2 Planned but NOT yet deployed (exists in OpenAPI / future batch)

> Per `_api_ground_truth.md`, these are in `docs/openapi/v1.yaml` but **not** in the deployed `route:list`:

- **Roster management** — invite / add / remove / list roster, invitation accept (the Roster *module* exists in code; its mobile HTTP surface is not deployed).
- **Notifications** API, **Privacy** (export/delete), full **Profile** (`GET/PATCH /me` beyond biometrics).
- **Admin** Dashboard, Users (customer mgmt), Admins, Lookups, Reports, Audit, Settings, Feature Flags, Notification Templates.

### 11.3 Transport reality (v1.2)

- **Traccar primary + SMS fallback are implemented and tested**; BLE is deferred (no code, no driver binding).
- **Actuation read-back proven against the fake** (event-forward output-on upgrades `dispatched` → `opened`); **real-driver fallback proven end-to-end** (offline Traccar → `device_offline` transient → SMS dispatch).
- **Whitelist is a no-op under Traccar** (platform-side authorisation) — the outbox completes immediately as `synced` and is retained as the audit / future-BLE channel.

### 11.4 Deferred by policy (`R-WF-13`)

Auto-renew (until Kapital tokenization), email receipts, PDF invoices, per-user access windows, MQTT driver,
partial-refund UI, SIM-credit collectors, whitelist drift audit, multi-tenancy (`R-WF-12` — **no `tenant_id`,
no `TenantScope`**). Reverb real-time push is deferred behind 1 s polling.

---

## 12. Risks & Open Items

| # | Risk / open item | Severity | Notes / source |
|---|---|---|---|
| 1 | **HB1 / T1 hardware test** — Traccar→`OUTPUT0` framing not yet proven on a real UMKa device | **High** (the one material open risk) | Seam/contract correct; only the wire detail (framing, output-bit attribute key, status-code mapping) is pending, all config-driven. (`FINAL_PHASE0_VERDICT`, `BATCH_09B_REVIEW` §7) |
| 2 | **SMS provider credentials** currently `fake` | High | Blocks live OTP, invitations, and the SMS fallback open path. (`DEPLOYMENT` §8) |
| 3 | **Kapital credentials empty** | High | Blocks real payments end-to-end; the integration, callback hardening, and reconciliation logic exist behind `FakeKapitalGateway`. (`DEPLOYMENT` §8) |
| 4 | **Off-site backup target** not configured | Medium | Only nightly local backup is verified; a single-box failure has no off-host copy. (`DEPLOYMENT` §8) |
| 5 | **Monitoring / alerting** not in place | Medium | Structured logs + thresholds are specified but no monitoring stack is deployed yet. (`DEPLOYMENT` §8) |
| 6 | **SSH hardening deferred** — root + password login, port 22 public (fail2ban only) | Medium | Key-based hardening pending. (`DEPLOYMENT` §4, §8) |
| 7 | **HSTS not enabled** | Low | TLS is Full(Strict); HSTS is on the pending list. (`DEPLOYMENT` §8) |
| 8 | **Single point of failure** — one VPS hosts app + Traccar + both DBs + Redis | Medium (pilot-acceptable) | By design for the pilot; mitigated by the Tier-2/3 split path in §10. |
| 9 | **Redis is single-instance** vs the `R-ARCH-10` Sentinel-HA target | Low (pilot) | HA is the Tier-3 target; the connection-level circuit breaker (503-not-hang) is the design guard. |
| 10 | **Vestigial column** `open_command_attempts.voice_gateway_id` always `null` under v1.2 | Informational | Left untouched to keep the 09-A control plane unchanged. (`BATCH_09B_REVIEW` §7) |
| 11 | **SMS field mapping** for the open-fallback gateway (`{sender,to,message}`) unconfirmed against the chosen AZ vendor | Low | Confirm at integration time. (`BATCH_09B_REVIEW` §7) |

**Bottom line.** The control plane, the modular-monolith boundaries, the data model, the security posture,
and the Traccar transport seam are implemented and verified against fakes (225 tests). The remaining risk is
concentrated in **live-credential wiring** (SMS, Kapital) and **one on-device validation** (HB1/T1) — each of
which has a documented fallback and is config-driven rather than requiring code change.

---

*End of Architecture Report — Salam Həyətimiz v1.2. Synthesised from the frozen v1.2 corpus and the deployed
backend on 2026-06-21. Where this report and a source disagree, the source governs (per `R-WF-05`,
spec-over-code).*
