# Salam Həyətimiz — Laravel Backend Architecture

**Version:** 1.2
**Status:** Active; v1.2 applies the approved transport pivot (see below)
**Changelog:** see [CHANGELOG.md](CHANGELOG.md) and [TRANSPORT_MIGRATION_CHANGELOG.md](TRANSPORT_MIGRATION_CHANGELOG.md). v1.1 applied the audit resolutions; **v1.2 (2026-06-14)** applies [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md): DeviceComm transports become **BLE (local) + Traccar (remote) + SMS (fallback)**; the CLIP driver, voice gateway, and `VoiceGatewaySelector` are retired. The `DeviceDriver` seam and the open-command pipeline are unchanged.
**Cross-references:**
- [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md)
- [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md)
- [openapi/v1.yaml](openapi/v1.yaml)
- [UI_UX_SPECIFICATION.md](UI_UX_SPECIFICATION.md)

**Stack:** Laravel 12, PHP 8.4, MariaDB 11, Redis 7, Laravel Horizon, Laravel Reverb.

---

## Table of Contents

1. Architectural Overview
2. Layering Principles
3. Top-level Folder Structure
4. Naming & Code Conventions
5. Pattern Decisions
   - Repository vs Eloquent
   - Service vs Action vs Job vs Listener
   - DTOs and Resources
6. Cross-cutting: HTTP Layer
   - Routing
   - Controllers
   - Form Requests
   - API Resources
   - Middleware (full inventory)
   - Exception Handling
7. Cross-cutting: Authentication & Authorization
   - JWT issuance
   - Policies (full inventory)
   - Gates
8. Cross-cutting: Event Bus
9. Cross-cutting: Queue Architecture (Horizon)
10. Cross-cutting: Scheduler
11. Cross-cutting: Caching, Locking, Idempotency
12. Cross-cutting: Configuration & Feature Flags
13. Cross-cutting: Container Bindings
14. Domain Modules
    - 14.1 Identity & Auth
    - 14.2 Users
    - 14.3 Catalog (Lookups)
    - 14.4 Devices
    - 14.5 Roster & Invitations
    - 14.6 DeviceComm (Driver Layer)
    - 14.7 Subscriptions
    - 14.8 Payments
    - 14.9 Notifications
    - 14.10 Audit
    - 14.11 Privacy
    - 14.12 Reporting
    - 14.13 Admin & Operations
15. Testing Topology
16. Open Architectural Questions

---

## 1. Architectural Overview

The backend is a **modular monolith** built on Laravel 12, organised by **bounded context** (`Domain/<Module>`) rather than by Laravel artifact type. Each domain module owns its data, business rules, events, and async work. Modules talk to each other only through:

1. **Public service classes** exposed by the module.
2. **Domain events** dispatched on the event bus.
3. **Read models** (Eloquent models scoped to read use cases).

There is no full hexagonal "ports & adapters" ceremony. We **lean on Laravel's strengths** (Eloquent, Horizon, the container) and add an indirection only where it pays for itself — at integration boundaries (payment gateway, SMS/voice provider, GSM driver), at hot paths needing testability (the open-command pipeline), and at security boundaries (auth issuance, signature verification).

```
+----------------------------------------------------------------------+
|                          HTTP / WebSocket                            |
|   Nginx -> PHP-FPM (controllers + Reverb)                            |
+--------------+----------------------+--------------------------------+
               |                      |
               v                      v
   +---------------------+   +---------------------+
   |  Form Requests      |   |  Channel Auth       |
   |  (validation)       |   |  (Reverb)           |
   +---------------------+   +---------------------+
               |
               v
   +---------------------+
   |  Controllers (thin) |   Delegate immediately into domain services
   +---------------------+
               |
               v
+----------------------------------------------------------------------+
|                        Domain Modules                                |
|  Auth | Users | Catalog | Devices | Roster | DeviceComm |            |
|  Subscriptions | Payments | Notifications | Audit | Privacy |        |
|  Reporting | Admin                                                  |
+--------+----------+-------------+------------+----------+------------+
         |          |             |            |          |
         v          v             v            v          v
   +-------+   +--------+   +----------+  +---------+  +------+
   |  DB   |   |  Redis |   |  Queue   |  |  Bus    |  | Cache|
   +-------+   +--------+   +----------+  +---------+  +------+
                                |             |
                                v             v
                       +-------------+   +-------------+
                       | Workers     |   | Listeners   |
                       | (Horizon)   |   | (sync/async)|
                       +-------------+   +-------------+
                                |
                                v
                  +-----------------------------+
                  | External Integrations       |
                  |  - Kapital Bank             |
                  |  - SMS provider             |
                  |  - Voice / GSM gateway      |
                  |  - FCM (push)               |
                  |  - S3-compatible storage    |
                  +-----------------------------+
```

---

## 2. Layering Principles

| Layer | Responsibility | Allowed dependencies |
|---|---|---|
| **HTTP** | Translate HTTP ↔ domain. Validate, authorise, present. | Domain (via DI) |
| **Domain Services** | Orchestrate use cases. Transaction boundaries live here. | Models, Repositories (rare), Adapters, other domain Services (via DI), event Bus |
| **Domain Models** | Data + invariant guards. Thin. | DB only |
| **Adapters / Integrations** | Outside-world I/O (Kapital, SMS, GSM, FCM). | HTTP clients, queues |
| **Jobs / Listeners** | Async execution of services. | Services |
| **Console / Scheduler** | Cron triggers. | Services |

Rules:

- A controller never touches a model directly. It calls a service, gets a DTO or model, and hands it to a Resource.
- A model never enqueues jobs or talks to integrations.
- Inter-module calls go through **the other module's `Services` namespace**, never through its models, jobs, or migrations.
- Cross-cutting concerns (`Audit`, `Notifications`) react to **events** — they are not called directly by feature code.

---

## 3. Top-level Folder Structure

```
salam/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── Subscriptions/
│   │   │   │   ├── SendExpiryRemindersCommand.php
│   │   │   │   └── ExpireSubscriptionsCommand.php
│   │   │   ├── Devices/
│   │   │   │   ├── RunDiagnosticsCommand.php
│   │   │   │   └── DetectOfflineDevicesCommand.php
│   │   │   ├── Payments/
│   │   │   │   └── ReconcileOrdersCommand.php
│   │   │   ├── Reporting/
│   │   │   │   ├── BuildDailyStatsCommand.php
│   │   │   │   └── RunReportJobsCommand.php
│   │   │   ├── Privacy/
│   │   │   │   ├── AnonymizeSelfDeletedUsersCommand.php
│   │   │   │   └── ProcessDataSubjectRequestsCommand.php
│   │   │   ├── Audit/
│   │   │   │   └── ArchivePartitionsCommand.php
│   │   │   └── System/
│   │   │       ├── PurgeExpiredOtpsCommand.php
│   │   │       └── PurgeExpiredIdempotencyKeysCommand.php
│   │   └── Kernel.php
│   │
│   ├── Domain/
│   │   ├── Auth/
│   │   ├── Users/
│   │   ├── Catalog/
│   │   ├── Devices/
│   │   ├── Roster/
│   │   ├── DeviceComm/
│   │   ├── Subscriptions/
│   │   ├── Payments/
│   │   ├── Notifications/
│   │   ├── Audit/
│   │   ├── Privacy/
│   │   ├── Reporting/
│   │   └── Admin/
│   │
│   ├── Http/
│   │   ├── Api/
│   │   │   └── V1/
│   │   │       ├── Controllers/
│   │   │       │   ├── Auth/
│   │   │       │   ├── Profile/
│   │   │       │   ├── Devices/
│   │   │       │   ├── Roster/
│   │   │       │   ├── Invitations/
│   │   │       │   ├── Commands/
│   │   │       │   ├── Subscriptions/
│   │   │       │   ├── Orders/
│   │   │       │   ├── Notifications/
│   │   │       │   ├── Privacy/
│   │   │       │   └── Health/
│   │   │       ├── Requests/
│   │   │       └── Resources/
│   │   ├── Admin/
│   │   │   └── V1/
│   │   │       ├── Controllers/
│   │   │       ├── Requests/
│   │   │       └── Resources/
│   │   ├── Webhooks/
│   │   │   └── Controllers/
│   │   │       ├── KapitalCallbackController.php
│   │   │       └── SmsInboundController.php
│   │   ├── Middleware/
│   │   ├── Resources/                 # Shared API resources
│   │   └── Kernel.php
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   ├── AuthServiceProvider.php
│   │   ├── EventServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   ├── HorizonServiceProvider.php
│   │   ├── BroadcastServiceProvider.php
│   │   ├── DomainServiceProvider.php  # Wires every domain module
│   │   └── IntegrationsServiceProvider.php
│   │
│   ├── Support/
│   │   ├── Concerns/              # Reusable traits (HasIdempotencyKey, …)
│   │   ├── Pagination/Cursor.php
│   │   ├── Money/Money.php
│   │   ├── Locale/LocaleResolver.php
│   │   ├── Phone/PhoneNumber.php
│   │   ├── Redaction/Redactor.php
│   │   └── Time/Clock.php          # Injectable clock for testability
│   │
│   ├── Exceptions/
│   │   ├── Handler.php
│   │   ├── Contracts/DomainException.php
│   │   └── Renderers/ApiExceptionRenderer.php
│   │
│   └── Broadcasting/
│       ├── ChannelAuth.php
│       └── Channels/
│           ├── UserChannel.php
│           └── DeviceChannel.php
│
├── bootstrap/
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── audit.php
│   ├── broadcasting.php
│   ├── cache.php
│   ├── domain/
│   │   ├── devices.php          # cooldowns, capacity, driver defaults
│   │   ├── subscriptions.php    # term, reminder windows
│   │   ├── payments.php         # provider, retry policy
│   │   ├── notifications.php    # default channels mask
│   │   ├── device_comm.php      # driver settings, timeouts
│   │   └── privacy.php          # anonymisation rules
│   ├── horizon.php
│   ├── integrations/
│   │   ├── kapital.php
│   │   ├── sms.php
│   │   ├── voice.php
│   │   └── fcm.php
│   ├── logging.php
│   ├── queue.php
│   ├── services.php
│   └── ... (Laravel defaults)
│
├── database/
│   ├── factories/
│   ├── migrations/
│   │   ├── 00_system/
│   │   ├── 01_identity_lookups/
│   │   ├── 02_identity_users/
│   │   ├── 03_identity_auth/
│   │   ├── 04_devices/
│   │   ├── 05_payments/
│   │   ├── 06_subscriptions/
│   │   ├── 07_device_ops/
│   │   ├── 11_notifications/
│   │   ├── 09_audit_ops/
│   │   ├── 10_stats/
│   │   └── 11_post_seed_constraints/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── Lookups/
│       └── NotificationTemplatesSeeder.php
│
├── lang/
│   ├── az/
│   ├── ru/
│   └── en/
│
├── public/
├── resources/
│   ├── views/
│   │   ├── admin/                # Blade admin panel
│   │   └── emails/               # Phase 2
│   ├── css/
│   └── js/
│
├── routes/
│   ├── api.php                   # /v1/*
│   ├── admin.php                 # /admin/v1/*
│   ├── webhooks.php
│   ├── channels.php              # Broadcasting auth
│   ├── console.php
│   └── web.php                   # Admin web routes (Blade)
│
├── storage/
├── tests/
│   ├── Unit/
│   ├── Feature/
│   │   ├── Api/
│   │   ├── Admin/
│   │   ├── Webhooks/
│   │   └── Console/
│   ├── Integration/              # Real DB, Redis, fake providers
│   └── Pest.php
│
└── docs/
    ├── TECHNICAL_SPECIFICATION.md
    ├── DATABASE_ARCHITECTURE.md
    ├── UI_UX_SPECIFICATION.md
    ├── BACKEND_ARCHITECTURE.md
    └── openapi/v1.yaml
```

### 3.1 Structure of a Domain Module

Every `app/Domain/<Module>/` folder follows the same internal shape (subfolders omitted if empty):

```
app/Domain/<Module>/
├── Models/                     # Eloquent models
├── Services/                   # Orchestration; public API of the module
├── Actions/                    # Single-purpose use cases (1 class, 1 method)
├── Repositories/               # Only when justified (see §5)
├── Queries/                    # Read-only query objects (read models)
├── Adapters/                   # External integrations behind interfaces
├── DTOs/                       # Immutable transport objects
├── Events/                     # Domain events
├── Listeners/                  # Module-local subscribers
├── Jobs/                       # Async work owned by this module
├── Policies/                   # Authorization
├── Rules/                      # Custom validation rules
├── Exceptions/                 # Domain-specific exceptions
├── Notifications/              # Mailables / NotificationDispatchers (rare)
├── Support/                    # Module-private helpers
└── ModuleServiceProvider.php   # Bindings, event maps, schedules
```

`ModuleServiceProvider` is auto-discovered by `DomainServiceProvider` (a `Providers/DomainServiceProvider.php` that iterates `app/Domain/*/` and registers each `ModuleServiceProvider` when present). This means a new module is added without touching `config/app.php`.

---

## 4. Naming & Code Conventions

| Concern | Convention |
|---|---|
| Files | One class per file, PSR-4 autoload. |
| Class names | PascalCase. Domain models singular (`Device`), collections via Eloquent. |
| Service classes | `<Noun>Service` for general orchestration (`SubscriptionService`); avoid generic "Manager". |
| Action classes | Imperative verb phrases (`OpenDevice`, `RegisterDevice`, `RefundOrder`). Exactly one `public function handle()` (or `__invoke()`). |
| Job classes | Imperative `<Verb>Job` (`DispatchOpenCommandJob`, `SendRenewalReminderJob`). |
| Listener classes | Descriptive past tense + the event name where useful (`AuditDeviceUserAdded`, `EnqueueWhitelistSyncOnRosterChanged`). |
| Event classes | Past tense, no `Event` suffix (`OpenCommandRequested`, `SubscriptionExpired`). |
| Exception classes | `<Domain><Reason>Exception` (`SubscriptionRequiredException`). |
| DTOs | `<Noun>Data` (`OpenDeviceData`, `OrderCreationData`); `readonly` classes. |
| Form requests | `<Verb><Noun>Request` (`CreateOrderRequest`, `OpenDeviceRequest`). |
| API resources | `<Noun>Resource` / `<Noun>Collection`. |
| Policies | `<Model>Policy`. |
| Repositories (when used) | `<Noun>Repository`. Interfaces in `Repositories/Contracts/`. |
| Adapters | `<Vendor>Client` (`KapitalBankClient`, `TraccarClient`) or `<Capability>Driver` (`TraccarDriver`). |
| Tables | snake_case plural. |
| Migrations | Numbered batches per `DATABASE_ARCHITECTURE.md` §11. |
| Routes | kebab-case path segments. |
| Database transactions | Always opened inside Services, never Controllers or Jobs. Jobs may *retry* but a Job's `handle()` calls a Service which opens the transaction. |
| Constants & enums | PHP 8.1 backed enums in `app/Domain/<Module>/Enums/`. |
| Code style | PSR-12 + Larastan level 8 + Rector L1 rules. |

---

## 5. Pattern Decisions

### 5.1 Repository pattern — Used Sparingly

We **do not** apply the repository pattern uniformly. Eloquent is already an active record + query builder; wrapping every model adds friction with little testability gain (Laravel's transactional rollback in tests, factories, and `Model::factory()` cover the common testability needs).

We use the **Repository** label for exactly three cases:

| Case | Example |
|---|---|
| **Crossing a real boundary** (HTTP/queue/external storage) | `KapitalBankClient`, `FcmPushClient`, `SmsProvider`, `TraccarClient`, `TraccarDriver` etc. — these implement *contracts* (interfaces) and are bound per environment. |
| **Read models needing per-call shape** | Query objects under `Queries/` for reporting (e.g. `DeviceFleetHealthQuery`) — they receive parameters and return DTOs/collections; not full repositories but the same intent. |
| **Hot path with hand-tuned SQL** | The open-permission check is encapsulated in `DeviceAccessQuery` so it can use a single optimised SQL with indexes, not 3 Eloquent calls. |

All other persistence happens through Eloquent models invoked from Services. No `UserRepository`, `DeviceRepository`, etc. unless one of the three cases above arises.

### 5.2 Service vs Action vs Job vs Listener — Decision Matrix

| If the operation is… | Then use… |
|---|---|
| A multi-step, transactional business operation with cohesive state changes | **Service** method. Example: `SubscriptionService::activateForOrder()` opens a transaction, mutates `subscriptions`, writes `subscription_periods`, dispatches events. |
| A single-purpose use case that maps 1:1 to a route or job | **Action** class. Example: `OpenDevice` invoked by `OpenDeviceController` and `OpenCommandRetryJob`. Actions are testable in isolation and reusable across HTTP/CLI/jobs. |
| The same operation as above but must run async | **Job** that internally calls the **Action**. The Job adds queue concerns (retry, backoff, middleware); the Action holds the logic. |
| A reaction to a domain event with no causal contract back to the caller | **Listener**. Example: `AuditDeviceUserAdded` writes to `audit_log` when `DeviceUserAdded` fires. |
| A simple "if X happens, queue Y" | **Listener that dispatches a Job** (or a job-mapped listener via `ShouldQueue`). |

The driver: **business logic lives in Services/Actions; Jobs and Listeners are thin wrappers**.

### 5.3 DTOs vs Eloquent Models vs Arrays

| Use case | What |
|---|---|
| Incoming HTTP request body | `FormRequest::validated()` → mapped into a **DTO** (`OpenDeviceData::fromRequest($req)`). Services never receive `Request` objects. |
| Outbound HTTP response | Eloquent model (or DTO) → `Resource` → JSON. |
| Cross-module call | DTO. Modules don't pass each other Eloquent models because mutation semantics across boundaries become unclear. |
| Internal within a module | Eloquent models are fine. |
| Inside a Job payload | DTO (because jobs serialise; passing Eloquent leans on the `SerializesModels` trait which queries DB on unserialize — acceptable for small models, but DTOs are explicit and faster). |

DTOs are **readonly** PHP 8.2 classes. We use Spatie's `laravel-data` package (pinned) to avoid hand-rolled mappers; it gives us validation, casts, and OpenAPI alignment for free.

### 5.4 API Resources

One `Resource` class per public response shape. Resources are explicit about every field — no `parent::toArray()` in production paths — because the OpenAPI is the binding contract.

Naming maps OpenAPI `components/schemas/<X>` → `App\Http\Api\V1\Resources\<X>Resource`. CI runs a script that diffs the OpenAPI schemas against actual resource outputs (via golden snapshots) to keep them aligned.

---

## 6. Cross-cutting: HTTP Layer

### 6.1 Routing

Three route files, each in its own kernel-bound group:

| File | Prefix | Middleware group | Audience |
|---|---|---|---|
| `routes/api.php` | `/v1` | `api`, `auth.user` | Mobile + technical mobile mode |
| `routes/admin.php` | `/admin/v1` | `api`, `auth.admin` | Admin panel + Blade SPA |
| `routes/webhooks.php` | `/v1/payments/callback`, `/v1/sms/inbound` | `api.webhook` | Bank, SMS provider |
| `routes/channels.php` | — | — | Broadcasting auth |

Routes group by feature; one **resource group per controller**. Route names use dotted notation aligned with the OpenAPI `operationId` (e.g. `Route::name('openDevice')`).

```
// Sketch of api.php structure (no code yet)
Route::middleware('api')->prefix('v1')->group(function () {
    Route::prefix('auth')->group(/* … */);
    Route::middleware('auth.user')->group(function () {
        Route::prefix('me')->group(/* … */);
        Route::prefix('devices')->group(/* … */);
        Route::prefix('subscriptions')->group(/* … */);
        Route::prefix('orders')->group(/* … */);
        Route::prefix('notifications')->group(/* … */);
        Route::prefix('privacy')->group(/* … */);
    });
});
```

### 6.2 Controllers

- One controller per resource group; **single-action controllers** for cross-cutting endpoints (`OpenDeviceController`, `PaymentCallbackController`).
- Controllers are **thin**: validate-via-FormRequest → resolve service → return Resource.
- Controllers never inject Eloquent models directly; route-model binding resolves models, but binding is constrained to the **scope visible to the caller** via `bindings()` overrides on FormRequest (e.g. `User` can only see *their* device id 42).
- No business logic in controllers. PHPStan custom rule (custom Larastan extension) flags controller methods longer than ~25 lines as smells.

### 6.3 Form Requests

- Each mutating endpoint has its own `FormRequest`.
- Validation rules live here, **not** in service/action classes — services can assume well-formed input.
- `authorize()` checks delegate to **Policies**, not inline logic.
- `validated()` array is mapped to a typed DTO via a factory method on the DTO class: `OpenDeviceData::fromValidated($r->validated())`.

### 6.4 API Resources

- One Resource per OpenAPI response schema.
- Composition over inheritance (`DeviceResource` *contains* a `SubscriptionBriefResource`, not extends).
- Locale-aware fields use `Lang::get` at the Resource boundary.

### 6.5 Middleware (full inventory)

All in `app/Http/Middleware/`. Bound in `app/Http/Kernel.php` under named groups.

| Middleware | Bound to | Purpose |
|---|---|---|
| `EnforceJson` | api | Reject non-JSON content types on mutations. |
| `RequestId` | api, web | Generates / propagates `X-Request-Id` (ULID); injects into log context. |
| `LogContext` | api, web | Adds `user_id`, `device_install`, route, request_id to all log records. |
| `LocaleNegotiator` | api | Resolves locale from `Accept-Language` ∩ user.preferred_language. |
| `RateLimiter` (Laravel built-in) | named buckets | Per-endpoint buckets defined in `RouteServiceProvider::configureRateLimiting()`. |
| `IdempotencyHandler` | mutating endpoints | Wraps the request: reads/writes `idempotency_keys` row keyed by `(scope, actor, key)`. Returns cached response on replay; rejects body-mismatch with `409`. |
| `EnsureMobileVersionSupported` | api | Reads `X-App-Version`; returns `426 update_required` when below minimum. |
| `EnforceMaintenanceMode` | api, web | Reads feature flag `maintenance.mobile|admin`; returns 503 if active (except `/health/*`). |
| `auth.user` | api (mobile) | Custom JWT guard `mobile`. Validates RS256, loads `User`, checks `status='active'`. |
| `auth.admin` | api admin | Custom JWT guard `admin`. Validates RS256, loads `AdminUser`, checks `status='active'`, enforces TOTP-step completion. |
| `EnforceTwoFactor` | api admin (write paths) | Requires `tfa_verified=true` claim on the admin JWT for state-mutating routes. |
| `BindRouteModels` | api | Replaces default route-model binding with policy-aware scoping. |
| `CaptureRawBody` *(v1.1, CRIT-07)* | webhooks | Runs first in the webhook stack. Stashes raw `php://input` bytes on the request before any JSON parsing, so HMAC verifiers can hash the exact wire bytes. Nginx is configured `proxy_request_buffering on;` and no body-rewriting modules sit on this path. |
| `VerifyKapitalSignature` *(v1.1, reads raw body from `CaptureRawBody`)* | webhooks | HMAC-SHA256 over the raw byte buffer; IP allowlist; on failure → 401 + log to `payment_logs(signature_valid=0)`. Emits separate metrics for `signature_invalid` partitioned by `ip_in_allowlist={true,false}` (CRIT-07). |
| `VerifySmsProviderSignature` | webhooks | Analogous for SMS inbound. |
| `RecordAuditContext` | api, admin | Sets `Audit::actor()` to user/admin for the duration of the request. |
| `EnforcePolicy` (helper) | per-route | Not a middleware per se; FormRequest `authorize()` invokes `Gate::authorize(...)` against the policy. |
| `CapturePerformance` | api, admin | Records p50/p95 latency to Prometheus per route via labels. |

### 6.6 Exception Handling

`app/Exceptions/Handler.php` is configured to:

1. Map domain exceptions (`SubscriptionRequiredException`, `CooldownException`, `DeviceDisabledException`, `IdempotencyMismatchException`, etc.) to the standard error envelope and HTTP code per OpenAPI.
2. Map validation exceptions to `ValidationErrorEnvelope`.
3. Catch unhandled exceptions, log with `request_id`, return `internal_error` with the `request_id` echoed.
4. Hide stack traces from non-debug environments.

Every domain exception extends `App\Exceptions\Contracts\DomainException` and implements:
- `httpStatus(): int`
- `errorCode(): string`
- `messageKey(): string`
- `details(): array|null`

This is what `ApiExceptionRenderer` reads, so adding a new domain error means one class — no Handler change.

---

## 7. Cross-cutting: Authentication & Authorization

### 7.1 JWT Issuance

- **Two disjoint guards**: `mobile` (issued from `Domain/Auth/Services/UserAuthenticator`) and `admin` (from `Domain/Auth/Services/AdminAuthenticator`).
- **Library**: `lcobucci/jwt` (pinned) directly, not a Laravel wrapper, to keep the signing/verification surface minimal and auditable.
- **Algorithm**: RS256. Key pair lives in `storage/keys/jwt-{mobile,admin}.pem`, loaded into `config/auth.php`; rotation procedure documented in runbook.
- **Mobile access token** (15 min): claims `sub=user.{id}`, `kind=user`, `fp=<install_fingerprint>`, `iat`, `exp`, `jti`, **`kid`** *(v1.1, CRIT-04)*.
- **Mobile refresh token**: opaque (random 32 bytes base64url); SHA-256 hashed in `refresh_tokens`. Rotated on each use.
- **Admin access token** (30 min): claims `sub=admin.{id}`, `kind=admin`, `role`, `tfa_verified=true|false`, `iat`, `exp`, `jti`, **`kid`** *(v1.1)*.
- **Revocation**: refresh tokens revoked in DB; access tokens revoked via a short-lived denylist in Redis keyed on `jti` (TTL = remaining lifetime).

#### 7.1.1 Key Identification & JWKS *(v1.1, CRIT-04)*

- Each signing key has a stable `kid` (e.g. `mobile-2026-q3`).
- Two `kid`s can be valid simultaneously during a rotation; the verifier picks the right public key by `kid` claim.
- **JWKS endpoint** at `GET /.well-known/jwks.json` on the **admin host only** exposes admin public keys. Mobile pins and does not consume JWKS; the endpoint exists to allow future mobile-key delivery without redesign.
- Per-secret rotation cadences and runbook references live in `TECHNICAL_SPECIFICATION.md` §15.5.1.

#### 7.1.2 Admin Recovery Codes *(v1.1, CRIT-09)*

`AdminAuthenticator::verifyTotp(challenge, code)` accepts either a TOTP code or a single-use recovery code. Recovery codes are bcrypt-hashed in `admin_users.recovery_codes_hashes` (JSON array of 8). On consumption, the matched slot is set to `null`. `AdminUserService::regenerateRecoveryCodes(admin)` returns a new set of 8 plaintext codes once and persists their hashes; the previous set is invalidated atomically.

### 7.2 Policies (full inventory)

Policies live in `app/Domain/<Module>/Policies/` and are auto-discovered by `AuthServiceProvider` via a `Gate::policy` map.

| Policy | Methods | Applies to |
|---|---|---|
| `UserPolicy` | `update`, `delete`, `view` | Self only |
| `DevicePolicy` | `view`, `open`, `rename`, `viewStats` | Mobile users |
| `DeviceOwnerPolicy` | `manageRoster`, `invite`, `revokeUser`, `transferOwnership` | Owners |
| `DeviceAdminPolicy` | `register`, `update`, `disable`, `enable`, `decommission`, `transfer`, `resyncWhitelist`, `pingDiagnostics`, `viewAuditedDetails` | Admins |
| `InvitationPolicy` | `create`, `view`, `accept`, `decline`, `cancel` | Owners + invitees |
| `SubscriptionPolicy` | `view`, `renew`, `toggleAutoRenew`, `cancel` | Sub owner (user or owner-payer) |
| `OrderPolicy` | `create`, `view`, `recheck` | Payer + admin |
| `RefundPolicy` | `create`, `view` | Super admin |
| `NotificationPolicy` | `view`, `markRead` | Recipient |
| `ConsentPolicy` | `view`, `record` | Self |
| `DataSubjectRequestPolicy` | `create`, `view` | Self + admin |
| `AdminUserPolicy` | `viewAny`, `create`, `update`, `offboard` | Super admin |
| `AuditPolicy` | `search`, `view` | Super admin (and technical with scoped reads) |
| `SettingPolicy` | `view`, `update` | Super admin |
| `FeatureFlagPolicy` | `view`, `update` | Super admin |
| `NotificationTemplatePolicy` | `view`, `update`, `upsertLocale` | Super admin |

### 7.3 Gates

Used for **coarse** role-based checks that aren't tied to a model:

| Gate | Check |
|---|---|
| `admin.role.super` | `auth()->user() instanceof AdminUser && role === 'super_admin'` |
| `admin.role.technical` | `role in ['technical','super_admin']` |
| `admin.tfa.verified` | JWT claim `tfa_verified=true` |
| `feature.flag` | Dynamic flag lookup with bucketing |
| `maintenance.bypass` | Super admins can bypass maintenance mode |

---

## 8. Cross-cutting: Event Bus

We use Laravel's event dispatcher. Listeners are **either** sync (for cheap reads/audit) or `ShouldQueue` (for IO-bound work).

Events live in `app/Domain/<Module>/Events/`. The `EventServiceProvider` discovers them per-module via the per-module `ModuleServiceProvider`.

### 8.1 Domain Events Catalogue

| Event | Producer module | Fired when | Typical subscribers |
|---|---|---|---|
| `OtpRequested` | Auth | After OTP row created | SMS dispatch (sync within job), Audit |
| `OtpVerified` | Auth | OTP successful, tokens about to issue | Audit, Notifications (welcome on first) |
| `UserRegistered` | Users | New `users` row | Audit, Notifications (welcome inapp) |
| `UserBlocked` / `UserUnblocked` | Admin | Block action | Audit |
| `UserConsentRecorded` | Privacy | New consent row | Audit |
| `UserSelfDeletionRequested` | Privacy | DSR or DELETE /me | Schedule anonymisation, Audit |
| `DeviceRegistered` | Devices | New device | Audit |
| `DeviceAssigned` | Devices | Owner attached | Audit, Notifications (owner) |
| `DeviceDisabled` / `DeviceEnabled` | Devices | Admin toggle | Audit, Notifications |
| `DeviceOwnershipTransferred` | Devices | Owner change | Audit, Notifications (both parties), Whitelist sync |
| `DeviceUserAdded` | Roster | Roster row activated | Audit, Notifications, DeviceComm (whitelist add) |
| `DeviceUserRevoked` | Roster | Roster row revoked | Audit, Notifications, DeviceComm (whitelist remove) |
| `InvitationCreated` | Roster | Pending row written | SMS dispatch, Audit |
| `InvitationAccepted` | Roster | Accept | Audit, Notifications (owner) |
| `InvitationDeclined` / `Cancelled` / `Expired` | Roster | Terminal | Audit, Notifications (owner) |
| `OpenCommandRequested` | Devices | Mobile sent open | Audit (deferred until terminal), DeviceComm (dispatch) |
| `OpenCommandDispatched` | DeviceComm | Driver acknowledged dispatch | none (waits for completion) |
| `OpenCommandCompleted` | DeviceComm | Terminal success | Audit, Notifications (optional inapp), Realtime broadcast |
| `OpenCommandFailed` | DeviceComm | Terminal failure | Audit, Notifications (push), Realtime broadcast |
| `DeviceDiagnosticReceived` | DeviceComm | Diagnostic stored | Update `devices.last_*`, alerting on offline |
| `WhitelistChangeRequested` | DeviceComm | Outbox row written | Drain job dispatched |
| `WhitelistChangeSynced` / `Failed` | DeviceComm | Drain terminal | Audit, Alerting on failed |
| `SubscriptionActivated` | Subscriptions | Sub goes active (initial / renewal) | Audit, Notifications (receipt), Whitelist add |
| `SubscriptionExpiringSoon` | Subscriptions | Reminder cron | Notifications |
| `SubscriptionExpired` | Subscriptions | Expiry sweep terminal | Audit, Notifications, Whitelist remove |
| `SubscriptionRenewed` | Subscriptions | Renewal applied | Audit, Notifications |
| `SubscriptionCancelled` / `Refunded` | Subscriptions | Refund / admin action | Audit, Notifications, Whitelist remove |
| `OrderCreated` | Payments | Order persisted | Audit |
| `OrderAuthorising` | Payments | Bank redirect issued | Audit |
| `OrderPaid` | Payments | Payment confirmed | Audit, Subscriptions::activate, Notifications (receipt) |
| `OrderFailed` / `Cancelled` / `Expired` | Payments | Terminal | Audit, Notifications |
| `OrderRefundRequested` | Payments | Admin clicked refund | Audit |
| `OrderRefunded` / `PartiallyRefunded` | Payments | Refund settled | Audit, Subscriptions (shorten or cancel), Notifications |
| `PaymentCallbackReceived` | Payments | Raw callback (before verification) | Persist to `payment_callbacks`, Audit |
| `PaymentCallbackProcessed` | Payments | Callback applied / duplicate / rejected | Audit |
| `NotificationDispatched` | Notifications | Sent to channel | metric only |
| `NotificationFailed` | Notifications | Provider error | Alerting |
| `AuditableActionPerformed` | (generic) | When code wants explicit audit not tied to a domain event | `Audit::write()` |
| `DataSubjectRequestSubmitted` | Privacy | DSR row created | Async builder Job, Audit |
| `DataSubjectRequestReady` | Privacy | Export packaged | Notifications |
| `ReportJobRequested` | Reporting | Admin queued export | Worker pickup |
| `ReportJobCompleted` / `Failed` | Reporting | Worker terminal | Notifications, Audit |

### 8.2 Subscription Discipline

- **Listeners are idempotent** — they handle re-delivery without side-effect duplication (e.g. notification dispatch uses `dedupe_key` from §13 of `DATABASE_ARCHITECTURE.md`).
- **Listeners never throw** through to the dispatcher in production — failures go to the failed-jobs table (for queued listeners) or are caught and logged (for sync listeners).
- **No listener calls another module's controller** — only Services. Cross-module fan-out is via events, not direct method calls.

---

## 9. Cross-cutting: Queue Architecture (Horizon)

Queues are managed by **Laravel Horizon**. Redis is the queue store.

### 9.1 Named Queues & Their Roles

| Queue | Purpose | Throughput target | Worker count (initial) | Tries | Backoff |
|---|---|---|---|---|---|
| `high` | Latency-sensitive jobs: open-command dispatch, payment-callback processing | 200 jobs/s burst | 8 procs | 3 | 1s, 4s, 10s |
| `default` | Catch-all for short business jobs | 50 jobs/s | 4 procs | 3 | 5s, 30s, 2m |
| `device-comm` | Transport-bound work (Traccar command dispatch, whitelist/credential sync, diagnostics; SMS fallback). Throttled per transport. | Transport-limited | Traccar/BLE/SMS worker profile | 5 | exponential w/ jitter |
| `notifications` | Push, SMS, inapp fan-out | 100 jobs/s | 6 procs | 5 | 10s, 1m, 5m, 15m, 1h |
| `payments` | Payment-system interactions (recheck, refund call, callback verification) | 30 jobs/s | 3 procs | 5 | 30s, 2m, 10m, 30m, 2h |
| `reports` | Long-running CSV/Parquet exports | 1 at a time per worker | 2 procs | 1 | n/a |
| `privacy` | Anonymisation, data export builders | low | 2 procs | 2 | 5m, 30m |

Horizon supervisor config maps each queue to a dedicated process group with these knobs.

### 9.2 Per-Queue Middleware

Job middleware (`app/Jobs/Middleware/`) applies per queue:

- **`RateLimited`** — Token-bucket per provider (SMS provider rate limits etc.).
- **`WithoutOverlapping`** — Per device on `device-comm` jobs to serialise commands per device.
- **`ThrottlesExceptions`** — Circuit-breaker for `payments` queue on Kapital outages.
- **`SkipIfActorBlocked`** — Drops jobs whose actor user is blocked (avoid wasted work).
- **`AuditFailures`** — On final-failure, writes to `audit_log` with `action=job.failed`.

### 9.3 Critical Jobs (illustrative — full list in §14)

| Job | Queue | Triggered by |
|---|---|---|
| `DispatchOpenCommandJob` | `high` | `OpenCommandRequested` event |
| `DispatchWhitelistChangeJob` | `device-comm` | `WhitelistChangeRequested` |
| `RunDeviceDiagnosticsJob` | `device-comm` | Scheduler (every 6 h, batched) |
| `ProcessPaymentCallbackJob` | `payments` | Webhook controller (enqueues then 200s the bank) |
| `RecheckOrderStatusJob` | `payments` | Scheduler (hourly) + manual admin recheck |
| `ApplyRefundJob` | `payments` | `OrderRefundRequested` |
| `SendPushNotificationJob` | `notifications` | `notification.queued` |
| `SendSmsNotificationJob` | `notifications` | `notification.queued` |
| `SendRenewalRemindersJob` | `notifications` | Scheduler (daily) |
| `ExpireSubscriptionsBatchJob` | `default` | Scheduler (daily) |
| `BuildDailyStatsJob` | `reports` | Scheduler (daily 02:30) |
| `RunReportJobWorker` | `reports` | `ReportJobRequested` |
| `AnonymizeUserJob` | `privacy` | DSR + scheduler |
| `BuildDataExportJob` | `privacy` | `DataSubjectRequestSubmitted` (kind=export) |
| `JanitorPurgeOtpsJob` | `default` | Scheduler (hourly) |
| `JanitorPurgeIdempotencyKeysJob` | `default` | Scheduler (15 min) |
| `ArchiveAuditPartitionJob` | `default` | Scheduler (monthly) |

### 9.4 Failure Handling

- **Failed jobs** retained in `failed_jobs` for 30 days.
- **Dead-letter alert**: On `JobFailed` event for jobs in queue `high` or `payments`, fire P1 alert (Telegram + email to oncall).
- **No silent drops** — circuit-breaker'd jobs are rescheduled with backoff; explicit drops (e.g. `SkipIfActorBlocked`) are logged.

---

## 10. Cross-cutting: Scheduler

`app/Console/Kernel.php` aggregates per-module schedules, each module exposes a static `register(Schedule $s)` in its `ModuleServiceProvider`.

| Cadence | Command | Module |
|---|---|---|
| every minute | `queue:health` | System |
| every 15 min | `idempotency:purge` | System |
| every hour | `otps:purge` | Auth |
| every hour | `payments:reconcile-orders` | Payments |
| every 6 h | `devices:run-diagnostics` | Devices |
| daily 01:00 Asia/Baku | `notifications:send-renewal-reminders` | Subscriptions |
| daily 02:00 Asia/Baku | `subscriptions:expire` | Subscriptions |
| daily 02:30 UTC | `reports:build-daily-stats` | Reporting |
| daily 03:00 UTC | `privacy:anonymize-self-deleted` | Privacy |
| daily 04:00 UTC | `devices:detect-offline` | Devices |
| weekly Mon 04:00 | `reports:purge-expired-exports` | Reporting |
| weekly Sun 04:00 *(v1.1, HIGH-02; Phase 2)* | `devices:audit-whitelist` | DeviceComm |
| monthly 1st 03:00 | `audit:roll-partitions` | Audit |

All schedule entries declare `withoutOverlapping()` and `onOneServer()` (uses Redis lock).

---

## 11. Cross-cutting: Caching, Locking, Idempotency

### 11.1 Cache

- **Driver**: Redis (`cache` connection).
- **Conventions**: Keys namespaced `salam:{env}:{domain}:{key}`. TTLs explicit, never `forever`.
- **Hot caches**:
  | Cache | Key | TTL | Invalidator |
  |---|---|---|---|
  | Device summary for mobile | `device:{id}` | 60 s | Device update events |
  | Settings | `settings:{key}` | 5 min | Setting update event |
  | Feature flags | `feature_flags:all` | 60 s | Flag update event |
  | Notification template render | `notif:{key}:{locale}:{hash}` | 1 h | Template update event |

  *(v1.1, HIGH-06)* The **open-permission check is intentionally NOT cached.** `DeviceAccessQuery::canOpen(user, device)` is a single SQL on `device_users` × `subscriptions` with a covering composite index; latency budget allows a primary-DB round trip per tap (~2 ms p99). Caching here would trade correctness (expired sub momentarily able to open) for negligible performance gain. This resolves the §3 / §11 contradiction in v1.0.

#### 11.1.1 Redis Logical DB Split *(v1.1, CRIT-05)*

Concerns are isolated onto separate Redis logical databases to bound memory pressure and to make per-concern migration possible later:

| DB | Concern | `maxmemory-policy` |
|---|---|---|
| 0 | application cache | `allkeys-lru` |
| 1 | queue (Horizon) | `noeviction` |
| 2 | locks (cooldowns, whitelist drain, scheduler) | `noeviction` |
| 3 | broadcasting (Reverb pub/sub) | `noeviction` |
| 4 | idempotency hot tier | `allkeys-lru` |

Redis runs with **Sentinel** (one primary + two replicas + three sentinels) or managed-Redis equivalent. Connection-level circuit breaker in `Cache::store('redis')` and `Queue::connection('redis')` returns `503` for opens when Redis is unhealthy — never hang.

### 11.2 Locking

- **Redis locks** (via Laravel's `Cache::lock`) for:
  - **Per-device whitelist drain** — `lock:device:{id}:whitelist` for the duration of one batch.
  - **Per-(user, device) cooldown** — `cd:{userId}:{deviceId}` `SETNX EX <ttl>`.
  - **Per-device global cooldown** — `cd:dev:{id}` `SETNX EX 2`.
  - **Scheduler one-server guards** — built-in `onOneServer`.
- **Locks are short-lived** (≤ 60 s); long operations break themselves into multiple-locked phases.

### 11.3 Idempotency

Two-tier storage: **Redis (hot)** + **DB `idempotency_keys` (durable)**.

Algorithm in `IdempotencyHandler` middleware:

1. Compute `(scope, actor_kind, actor_id, key)` from route name + auth + header.
2. Lookup Redis cache; on hit, return stored response.
3. On miss, lookup DB; on hit, hydrate Redis and return.
4. On miss in both, `INSERT … ON CONFLICT DO NOTHING` a `pending` row.
5. If insert succeeded, pass request through; on terminal response, write `response_status/body` and TTL it into Redis.
6. If insert failed (race), wait briefly and read; if still `pending` after a short timeout, return `409 conflict` (concurrent request with same key).
7. On replay with same key but mismatching `request_hash`, return `409 idempotency_mismatch`.

The DB row is the source of truth; Redis is a hot cache for replay speed.

---

## 12. Cross-cutting: Configuration & Feature Flags

### 12.1 Configuration Sources

- **Static**: `config/*.php` (env-driven via `env()`, but only at config-time, never in domain code — `php artisan config:cache` is mandatory in prod).
- **Runtime tunables**: `settings` table (DB). Read via `Settings::get('prices.sub_main_minor', default)` which is cached.
- **Feature flags**: `feature_flags` table; evaluated via `FeatureFlag::active('flag_key', user)` with user-bucket hashing.

### 12.2 Decision Rule

| Property | Where it lives |
|---|---|
| Secrets (API keys, signing keys) | Env / Vault. Never DB. |
| Provider URLs and timeouts | Config files. Rare changes; deploy-time. |
| Prices, cooldowns, TTLs that ops may tune | `settings` table. Audited. |
| Booleans / rollouts | `feature_flags` table. |

Each `Settings::get()` and `FeatureFlag::active()` call carries a **default fallback** so a missing or misconfigured setting cannot brick a feature.

---

## 13. Cross-cutting: Container Bindings

All bindings live in **one of**:
- `IntegrationsServiceProvider` — vendor-replaceable interfaces (`TraccarClient`, `SmsProvider`, `PushClient`, `PaymentGateway`, `ObjectStorage`). *(v1.2: `VoiceGateway` retired.)*
- Each `ModuleServiceProvider` — module-private contracts and decorators.
- `AuthServiceProvider` — guard / provider bindings.

### 13.1 Integration Interfaces (representative)

| Interface | Default impl | Test impl | Notes |
|---|---|---|---|
| `App\Domain\DeviceComm\Adapters\TraccarClient` *(v1.2)* | `HttpTraccarClient` | `FakeTraccarClient` | Remote command (→ `cmdout.p`) + event-forward ingestion. **Replaces the retired `VoiceGateway`.** |
| `App\Domain\DeviceComm\Adapters\SmsProvider` | `AzercellSmsProvider` (whatever AZ provider wins Phase 0) | `FakeSmsProvider` | SMS dispatch + inbound webhook (open fallback path) |
| `App\Domain\DeviceComm\Contracts\DeviceDriver` | `TraccarDriver`, `SmsDriver`, `BleProvisioningDriver` *(v1.2)* | `FakeDeviceDriver` | Resolved per `device.driver_type` by `DriverResolver`. *(CLIP/Hybrid/Mqtt drivers retired.)* |
| `App\Domain\Payments\Adapters\PaymentGateway` | `KapitalBankClient` | `FakeKapitalGateway` | Hosted-page, callbacks, getOrderStatus, refund |
| `App\Domain\Notifications\Adapters\PushClient` | `FcmPushClient` | `FakePushClient` | FCM (Android + iOS via FCM) |
| `App\Domain\Reporting\Adapters\ObjectStorage` | `S3ObjectStorage` | `FakeObjectStorage` | Exports |
| `App\Support\Time\Clock` | `SystemClock` | `FixedClock`/`OffsetClock` | Inject `now()` for testability |

Tests bind fakes through `$this->swap()` or the Laravel test container. Integration tests bind real implementations only against sandboxes.

---

## 14. Domain Modules

For every module:

- **Owns**: tables, models, services, jobs, listeners, policies, events.
- **Public API**: `Services/` and `Events/` namespaces — these are what other modules may depend on.
- **Internal**: everything else; treat as private.

Order below mirrors the dependency direction (later modules may depend on earlier).

---

### 14.1 Identity & Auth (`Domain/Auth`)

**Purpose** — OTP issuance and verification, JWT issuance, refresh-token rotation, biometric enrollment, auth-attempt logging.

**Models** — `Otp`, `RefreshToken`, `AuthAttempt`, `UserDevice` (mobile install).

**Services**
- `OtpService` — `request(phone, purpose)`, `verify(phone, code, fingerprint)`.
- `UserAuthenticator` — `issue(user, fingerprint)`, `refresh(refresh_token, fingerprint)`, `logout(refresh_token)`.
- `AdminAuthenticator` — `loginStep1(email, password)`, `verifyTotp(challenge, code)`, `logout(jti)`.
- `BiometricService` — `enroll(install)`, `disable(install)`.
- `RefreshTokenJanitor` — `purgeExpired()`.

**Actions**
- `IssueOtp`, `VerifyOtp`, `IssueMobileTokens`, `RotateRefreshToken`, `EnrollBiometrics`.

**Adapters**
- Uses `SmsProvider` (from DeviceComm) for OTP dispatch.

**Events** — `OtpRequested`, `OtpVerified`, `RefreshTokenRotated`, `AuthLockedOut`, `AdminAuthSucceeded`, `AdminAuthFailed`.

**Jobs** — `SendOtpSmsJob` (queue: `high`).

**Policies** — None (auth is pre-policy).

**Exceptions** — `OtpInvalidException`, `OtpExpiredException`, `OtpMaxAttemptsException`, `RefreshTokenInvalidException`, `AuthLockedException`.

---

### 14.2 Users (`Domain/Users`)

**Purpose** — Mobile user profile, language, consents-link, soft-delete lifecycle.

**Models** — `User`, `UserConsent` (linked from Privacy).

**Services**
- `UserProfileService` — `update(user, dto)`, `selfDelete(user)`, `restore(user)`.
- `UserFinder` — resolve-or-create-by-phone (used by Auth, Roster, Devices).

**Events** — `UserRegistered`, `UserProfileUpdated`, `UserSelfDeletionRequested`, `UserAnonymized`.

**Policies** — `UserPolicy` (self only).

**Exceptions** — `UserBlockedException`, `UserHasActiveOwnedDevicesException` (blocks self-delete).

---

### 14.3 Catalog / Lookups (`Domain/Catalog`)

**Purpose** — SIM operators, device models, regions. Reference data only.

**Models** — `SimOperator`, `DeviceModel`, `Region`.

**Services** — `CatalogQuery` (read facade with caching).

**Events** — `LookupUpdated` (admin edits).

**Policies** — Admin-only.

---

### 14.4 Devices (`Domain/Devices`)

**Purpose** — Device lifecycle: registration, assignment, status transitions, location, signal/health metadata.

**Models** — `Device`.

**Services**
- `DeviceRegistrar` — `register(dto)` (Tech / Admin).
- `DeviceAssigner` — `assign(device, ownerPhone, ...)`.
- `DeviceStateMachine` — `disable`, `enable`, `decommission`, `transferOwnership`. Each opens a transaction, validates from/to, writes events and audit.
- `DeviceLookup` — `forUser(user)`, `forOwner(user)`, find-with-policy.
- `DeviceUpdater` — `update(device, dto)`.

**Queries** — `DeviceAccessQuery::canOpen(user, device)` (single optimised SQL).

**Events** — `DeviceRegistered`, `DeviceAssigned`, `DeviceUpdated`, `DeviceDisabled`, `DeviceEnabled`, `DeviceDecommissioned`, `DeviceOwnershipTransferred`.

**Policies** — `DevicePolicy`, `DeviceAdminPolicy`.

**Exceptions** — `DeviceDisabledException`, `DeviceAlreadyAssignedException`, `DeviceNotInStateException`.

---

### 14.5 Roster & Invitations (`Domain/Roster`)

**Purpose** — Who can use a device, and the invitation flow that puts them on the roster.

**Models** — `DeviceUser`, `DeviceUserHistory`, `Invitation`.

**Services**
- `RosterService` — `addUser`, `revokeUser`, `changeRole`.
- `InvitationService` — `create(device, owner, dto)`, `accept(token, user)`, `decline(token, user)`, `cancel(invitation)`, `expireBatch()`.

**Events** — `DeviceUserAdded`, `DeviceUserRevoked`, `InvitationCreated`, `InvitationAccepted`, `InvitationDeclined`, `InvitationExpired`, `InvitationCancelled`.

**Jobs** — `SendInvitationSmsJob`, `ExpireInvitationsBatchJob`.

**Policies** — `DeviceOwnerPolicy`, `InvitationPolicy`.

**Exceptions** — `RosterCapacityException`, `LastOwnerCannotBeRevokedException`, `InvitationExpiredException`, `InvitationAlreadyAcceptedException`.

**Listeners (cross-module reaction)** —
- On `SubscriptionActivated` → `RosterService::ensureWhitelisted(user, device)` (when sub flips active for an existing device_user).
- On `SubscriptionExpired` → `RosterService::scheduleWhitelistRemoval()`.

---

### 14.6 DeviceComm — Driver Layer (`Domain/DeviceComm`)

> **v1.2 transport pivot.** Hardware is the GLONASSSoft **UMKa 310 v2L** telematics tracker (Wialon IPS/Combine), not a CLIP GSM relay. Transports: **`traccar`** (remote primary + telemetry + command), **`ble`** (local/in-person primary), **`sms`** (emergency fallback). The `DeviceDriver` interface, `DriverResolver`, the open-command state machine, the whitelist outbox, cooldown, and `expected_completion_ms` are **unchanged** (built in batch 09-A). The CLIP driver, `VoiceGateway`(+`VoiceGatewaySelector`), and `OperatorFallbackPolicy` are **retired** (CRIT-01 & CRIT-03 retired). Concrete drivers + Traccar integration land in batch 09-B (see [BATCH_09B_SCOPE.md](BATCH_09B_SCOPE.md)).

**Purpose** — Translate domain intents (open, provision, diagnose) into device actions via the resolved transport: Traccar command over the device's live Wialon session, a local BLE trigger, or an SMS fallback. All converge on the on-device `cmdout.p` → 1-second relay pulse.

**Models** — `OpenCommand`, `OpenCommandAttempt`, `OpenCommandFeedback`, `WhitelistChange`, `DeviceDiagnostic` *(diagnostics table created in 09-B)*. *(v1.2 adds a BLE-entitlement/credential table and a Traccar device-mapping table — DB Arch §6.x, 09-B.)*

**Services**
- `OpenCommandService` — open-command lifecycle + state machine (built in 09-A).
- `WhitelistService` — outbox enqueue/resync (built in 09-A); under v1.2 it carries device-config / BLE-credential provisioning + Traccar authorisation sync.
- `DiagnosticsService` — `recordDiagnostic(device, dto)` from Traccar telemetry; `pingNow(device)` via Traccar (09-B).

**Actions**
- `DispatchOpenCommand` (`CommandDispatcher`) — resolves the driver, fires, runs the single fallback, updates state (built in 09-A).
- `ApplyWhitelistChange` (`WhitelistSyncJob` drain) — resolves the driver, applies, retries (09-B).

**Adapters (Driver Layer)**
- `DeviceDriver` interface: `open`, `whitelistAdd`, `whitelistRemove`, `diagnose`, `supports` — **unchanged**.
- **`TraccarDriver`** (remote, via `TraccarClient` REST), **`BleProvisioningDriver`/local-open coordinator**, **`SmsDriver`** (fallback); `FakeDeviceDriver` is the test double. Resolved per `device.driver_type` by `DriverResolver`.
- `DriverResolver::for(Device)` — returns the driver instance from the container (`device-driver.<type>`). The per-operator `OperatorFallbackPolicy` is **retired**.
- `TraccarClient` — REST command (custom/output → `cmdout.p`), event-forward ingestion (positions, I/O/output state, command results, online status).

**Driver fallback** *(HIGH-03)* — `CommandDispatcher`: on a transient failure it retries with `device_models.fallback_open_driver` once (typically `sms` when offline from Traccar). Both attempts persist to `open_command_attempts`. Non-transient failures do not fall back.

**Events** — `OpenCommandRequested`, `OpenCommandDispatched`, `OpenCommandCompleted`, `OpenCommandFailed`, `WhitelistChangeRequested`, `WhitelistChangeSynced`, `WhitelistChangeFailed`, `DeviceDiagnosticReceived`.

**Jobs** — `DispatchOpenCommandJob` (queue: `high`), `ApplyWhitelistChangeJob` (queue: `device-comm`), `RunDeviceDiagnosticsJob` (queue: `device-comm`), `ExpireStaleOpenCommandsJob` (queue: `default`).

**Job middleware** — `WithoutOverlapping` per device on whitelist; `ThrottlesExceptions` per provider on dispatch; cool-down lock acquisition is **inside the action** for the open path (already enforced by HTTP, but defence-in-depth here).

**Policies** — Mostly via `DevicePolicy::open()` upstream; this module trusts its inputs.

**Exceptions** — `DeviceOfflineException`, `DriverUnsupportedException`, `CooldownException`.

---

### 14.7 Subscriptions (`Domain/Subscriptions`)

**Purpose** — Per-(user, device) entitlement lifecycle: pending → active → expired/cancelled/refunded, plus renewal & history.

**Models** — `Subscription`, `SubscriptionPeriod`.

**Services**
- `SubscriptionService` — `createPending(deviceUser, tier)`, `activate(sub, orderId, periodStart, periodEnd)`, `extend(sub, days)`, `expire(sub)`, `cancel(sub, reason)`.
- `RenewalService` — `initiateRenewal(sub, user, returnUrl)` — orchestrates with Payments to create an order.
- `AutoRenewService` (P2) — `attempt(sub)`.
- `ExpirySweep` — `runBatch()` (called by command), `findExpiring(d)` for reminders.

**Queries** — `SubscriptionStatusQuery::for(user, device)`.

**Events** — `SubscriptionActivated`, `SubscriptionRenewed`, `SubscriptionExpiringSoon`, `SubscriptionExpired`, `SubscriptionCancelled`, `SubscriptionRefunded`.

**Jobs** — `SendRenewalRemindersJob`, `ExpireSubscriptionsBatchJob`, `AttemptAutoRenewalJob` (P2).

**Listeners** — On `OrderPaid` with `purpose ∈ {sub_main, sub_additional, sub_renewal}` → `SubscriptionService::activate()`.

**Policies** — `SubscriptionPolicy`.

**Exceptions** — `SubscriptionNotEligibleForRenewalException`, `SubscriptionAlreadyActiveException`.

---

### 14.8 Payments (`Domain/Payments`)

**Purpose** — Orders, payment events, refunds, Kapital integration.

**Models** — `Order`, `OrderItem`, `Payment`, `Refund`, `CardToken`, `PaymentCallback`, `PaymentLog`.

**Refund pro-rata math** *(v1.1, HIGH-08)* — `RefundService::executeApprovedRefund` applies the algorithm specified in `TECHNICAL_SPECIFICATION.md` §14.5.1 to determine whether the subscription is `refunded` (full) or its `ends_at` shortened (partial). Each refund creates a negative `subscription_periods` row and writes `audit_log`.

**Services**
- `OrderService` — `create(payer, dto)` → opens transaction, persists order/items, calls gateway, stores `bank_redirect_url`.
- `PaymentCallbackService` — `receive(payload, signature, ip)` → idempotent persist + dedupe, schedules `ProcessPaymentCallbackJob`.
- `PaymentVerifierService` — Called by job; calls `getOrderStatus`, applies result to order/payments/subscription.
- `RefundService` — `request(order, amount, reason, admin)`, `executeApprovedRefund(refund)`.
- `OrderReconciler` — Periodic recheck for stuck `authorising` orders.
- `PaymentLogger` *(v1.1, HIGH-15)* — Wraps every gateway call. Uses **allowlist** serialization: only fields explicitly on the allowlist (`orderId`, `status`, `amount`, `currency`, `pan_masked`, `cardBrand`, `rrn`, `approvalCode`, …) reach `payment_logs`; everything else is dropped before encryption. The serialized JSON is then app-layer encrypted and stored in `payment_logs.request_redacted_encrypted` / `response_redacted_encrypted`. A daily `PaymentLogsScannerJob` regex-scans the last 7 days for PAN-like and CVV-like patterns; findings fire a P1 alert.

**Adapters**
- `PaymentGateway` interface — `registerOrder`, `getOrderStatus`, `refund`, `cancel`.
- `KapitalBankClient` implementation; HTTP client (Guzzle) injected with timeouts, retries via `Http::retry()`, signed-request helpers.

**Events** — `OrderCreated`, `OrderAuthorising`, `PaymentCallbackReceived`, `PaymentCallbackProcessed`, `OrderPaid`, `OrderFailed`, `OrderCancelled`, `OrderExpired`, `OrderRefundRequested`, `OrderRefunded`, `OrderPartiallyRefunded`.

**Jobs** — `ProcessPaymentCallbackJob` (queue: `payments`), `RecheckOrderStatusJob` (queue: `payments`), `ApplyRefundJob` (queue: `payments`), `ExpireStaleOrdersJob` (queue: `default`).

**Policies** — `OrderPolicy`, `RefundPolicy`.

**Exceptions** — `PaymentProviderUnavailableException`, `OrderNotRefundableException`, `SignatureInvalidException`, `IdempotencyMismatchException` (callback level).

---

### 14.9 Notifications (`Domain/Notifications`)

**Purpose** — Template-driven, multi-channel, idempotent fan-out.

**Models** — `NotificationTemplate`, `NotificationTemplateLocale`, `Notification`, `UserNotificationSetting`.

**Services**
- `NotificationDispatcher` — `dispatch(user, templateKey, variables, dedupeKey = null, channels = null)`.
- `TemplateRenderer` — Cached-render of `(template_key, locale, vars)` → subject + body.
- `NotificationSettingService` — read/update user preferences.
- `PushTokenService` — register/refresh FCM token via `user_devices`.

**Adapters**
- `PushClient` (FCM).
- Uses `SmsProvider` (from DeviceComm).

**Events** — `NotificationQueued`, `NotificationDispatched`, `NotificationFailed`.

**Jobs** — `SendPushNotificationJob`, `SendSmsNotificationJob`, `SendEmailNotificationJob` (P2). All on `notifications` queue.

**Policies** — `NotificationPolicy`.

**Listeners (very many; this module is the major event consumer)** — see §8.1 for the full reactor map. Each listener is one class that calls `NotificationDispatcher::dispatch(...)`.

**Exceptions** — `TemplateMissingException`, `TemplateRenderException`.

**Admin-initiated (Reconciliation §C / Tech Spec §17.6)** — adds the `NotificationCampaign` model, an admin campaign dispatch path (resolve audience → distinct `user_id` → chunked fan-out into per-channel `notifications` rows via the same `NotificationDispatcher` / `PushClient`), and `NotificationCampaignPolicy` (`notifications.view` / `notifications.send`; `complex_manager`-scoped). Admin free-text is **non-templated** (reserved `template_key='system.admin_campaign'`); business notifications stay template-driven (hybrid).

**Visitor-opened consumer (VL110C only)** — a listener on `OpenCommandCompleted` filtered to `state=Opened && source=Visitor` dispatches the resident's "visitor opened" notification (§8.1 reactor map). UMKa is out of scope.

---

### 14.10 Audit (`Domain/Audit`)

**Purpose** — Capture every privileged action in an immutable, queryable log.

**Models** — `AuditLog`.

**Services**
- `Audit` (facade) — `actor(actor)`, `write(action, entity, payload)`.
- `AuditSearchQuery` — admin-facing search with cursor pagination.
- `ActionLabeler` — Maps technical event names to human-readable actions in the right locale.

**Listeners** — Generic listener `RecordAuditFromEvent` registered against the curated event set in §8.1. Each event provides `auditAction()` and `auditPayload()` methods so the listener stays generic.

**Policies** — `AuditPolicy` (super admin and scoped technical).

**Schedule** — Monthly partition roll.

---

### 14.11 Privacy (`Domain/Privacy`)

**Purpose** — Consents, DSR (export / deletion), anonymisation.

**Models** — `UserConsent`, `DataSubjectRequest`.

**Services**
- `ConsentService` — `record(user, kind, version, granted)`.
- `DataSubjectRequestService` — `submitExport(user)`, `submitDeletion(user)`, `markReady(req, url)`.
- `AnonymizationService` — `anonymize(user)`. Deterministically replaces PII with hashes.

**Events** — `UserConsentRecorded`, `DataSubjectRequestSubmitted`, `DataSubjectRequestReady`, `UserAnonymized`.

**Jobs** — `BuildDataExportJob`, `AnonymizeUserJob`.

**Policies** — `ConsentPolicy`, `DataSubjectRequestPolicy`.

**Schedule** — Daily anonymise sweep over soft-deleted users older than 30 d.

---

### 14.12 Reporting (`Domain/Reporting`)

**Purpose** — Materialised daily stats + async report jobs.

**Models** — `DeviceDailyStat`, `RevenueDailyStat`, `SubscriptionDailyStat`, `ReportJob`.

**Services**
- `DailyStatsBuilder` — `buildFor(date)` (idempotent re-run safe).
- `ReportJobService` — `request(admin, kind, params)`, `run(job)`.

**Queries** — `RevenueRangeQuery`, `DeviceFleetHealthQuery`, `SubscriptionRangeQuery`.

**Adapters** — `ObjectStorage` (S3-compatible).

**Events** — `ReportJobRequested`, `ReportJobStarted`, `ReportJobCompleted`, `ReportJobFailed`.

**Jobs** — `RunReportJobWorker`, `BuildDailyStatsJob`.

**Policies** — Admin-only.

---

### 14.13 Admin & Operations (`Domain/Admin`)

**Purpose** — Admin user management, settings, feature flags, notification template management (the admin-side of templates).

**Models** — `AdminUser`, `Setting`, `FeatureFlag`.

**Services**
- `AdminUserService` — CRUD.
- `SettingService` — get/set with caching invalidation; emits `LookupUpdated`.
- `FeatureFlagService` — get/set with bucketing.

**Events** — `AdminUserCreated`, `AdminUserOffboarded`, `SettingChanged`, `FeatureFlagChanged`, `NotificationTemplateUpdated`.

**Policies** — `AdminUserPolicy`, `SettingPolicy`, `FeatureFlagPolicy`, `NotificationTemplatePolicy`.

---

## 15. Testing Topology

Aligns with `TECHNICAL_SPECIFICATION.md` §20.

| Layer | Folder | Notes |
|---|---|---|
| Unit | `tests/Unit/` | Pure functions, services with mocked deps. Pest. |
| Feature (HTTP) | `tests/Feature/Api/`, `tests/Feature/Admin/`, `tests/Feature/Webhooks/` | Full request → response. SQLite in-memory not allowed for these — use MariaDB to exercise real SQL features. |
| Integration | `tests/Integration/` | Real DB + Redis + Horizon, fake external adapters. |
| Console | `tests/Feature/Console/` | Artisan command invocations. |
| Contract | CI step | OpenAPI ↔ Resource diff. |

Each domain module **owns** its tests under matching folders, but global feature tests sit in `tests/Feature/`.

Test container bindings swap real integrations for fakes via `IntegrationsServiceProvider::register()` reading `app()->environment('testing')`.

### 15.1 CI Integrity Checks *(v1.1)*

In addition to lint and unit/feature tests, the CI pipeline runs the following invariant checks that fail the build:

| Check | What it verifies | Resolves |
|---|---|---|
| `ci:grants:audit-log-immutable` | The runtime DB user has no `UPDATE` or `DELETE` privilege on `audit_log` or `payment_logs` (queries `information_schema.user_privileges`). | HIGH-13 |
| `ci:openapi:resources-match` | Every response shape registered in `app/Http/.../Resources/` matches its OpenAPI `components/schemas/<X>` (golden-snapshot diff). | — |
| `ci:openapi:operationIds-unique` | `php docs/openapi/validate.php` passes (`$ref` resolution + unique operationIds + declared tags). | — |
| `ci:device-users-uniqueness` | Concurrency test: 100 simultaneous inserts of same `(device_id, user_id)` result in exactly 1 success. | HIGH-07 |
| `ci:payment-logs:no-pan` | Regex scan of last 7 days of `payment_logs` for PAN-/CVV-like patterns; any hit fails. | HIGH-15 |

---

## 16. Open Architectural Questions

These are deliberate, called-out non-decisions to revisit before/during Phase 1.

1. **WebSocket transport** — Reverb is the default plan; if scale/operability becomes an issue we may switch to Soketi or a managed Pusher-compatible service. The `Broadcasting/` boundary keeps this swap small.
2. **JWT signing library** — `lcobucci/jwt` chosen for minimal surface; if it lags behind PHP 8.x JWT RFCs, evaluate `firebase/php-jwt`.
3. **Repositories vs Eloquent** — §5.1 declares the policy. Re-evaluate after Phase 1 if any module's tests become hard to write or if a second persistence target appears.
4. **Spatie laravel-data dependency** — Pinned, but adds a dependency. If team prefers hand-rolled DTOs, swap by mechanical refactor (no logic change).
5. **Single monolith vs microservices** — Modular monolith for Phase 1–3; modules are decomposable later. The driver layer is the most likely first extraction (separate process running closer to GSM hardware).
6. **Audit log polymorphic FK** — Accepted no-FK on `audit_log.actor_id`; verified by a CI integrity check script every release.
7. **`maintenance.bypass` Gate** — Bypass for super admins planned; review if support engineers need partial bypass.

---

*End of Backend Architecture v1.0.*
