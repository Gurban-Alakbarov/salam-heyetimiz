# Batch 09-A — DeviceComm Core — Review Package

> **v1.2 forward note (2026-06-14).** The transport reassessment was approved
> ([FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md)). The 09-A core stands **unchanged and
> valid** — the `DeviceDriver` seam, open-command lifecycle, cooldown, and whitelist outbox all carry
> over. The "deferred to the GSM batch" items in §6 now map to **Traccar / BLE / SMS** (not CLIP/voice):
> see [BATCH_09B_SCOPE.md](BATCH_09B_SCOPE.md). The only coordinated change touching 09-A code is the
> `DriverType` enum (`clip_sms…` → `traccar/ble/sms`), executed at the start of 09-B.

**Version:** 1.0
**Date:** 2026-06-14
**Scope:** the DeviceComm bounded-context **core** only — open-command lifecycle + state machine,
cooldown enforcement, the command queue, the whitelist outbox + sync tracking, command/stats reads,
and the Devices ownership-event reactions. **The driver layer is interfaces only** — no GSM modem
communication, SMS, CLIP, voice gateway, or operator-specific drivers (those land in the GSM batch).
**Sources of truth:** PROJECT_CONSTITUTION §R-GSM-01..13 / R-DOM-05/07, TECHNICAL_SPECIFICATION §12,
DATABASE_ARCHITECTURE §6.1–6.3, BACKEND_ARCHITECTURE §14.6, openapi/v1.yaml.

## Verification (run on Windows / PHP 8.2.12 / MariaDB 10.4.32)

| Step | Result |
|---|---|
| `php artisan migrate` | ✅ 4 new tables: `open_commands` (partitioned), `open_command_attempts`, `open_command_feedback`, `whitelist_changes` |
| `php artisan test` (full suite) | ✅ **202 passed, 673 assertions**, 0 failed (159 prior + **43 DeviceComm**) |
| `php artisan route:list` | ✅ 75 routes total; **8 new** (5 mobile + 3 admin) |
| `php docs/openapi/validate.php` | ✅ green (100 operationIds, 96 refs resolve, 26 tags) |

---

## 1. File tree (batch 09-A)

```
database/migrations/07_device_ops/
├── ..._create_open_commands_table.php            # RANGE-partitioned (raw SQL), PK (id, partition_key), no FKs
├── ..._create_open_command_attempts_table.php    # no FK to partitioned parent (app-enforced)
├── ..._create_open_command_feedback_table.php    # user_id FK; no FK to partitioned parent
└── ..._create_whitelist_changes_table.php        # outbox; seq = id (monotonic drain key)
app/Domain/DeviceComm/
├── ModuleServiceProvider.php                      # OpenCommandPolicy + 3 device-event listeners
├── Contracts/DeviceDriver.php                     # R-GSM-01 interface (open/whitelistAdd/Remove/diagnose/supports)
├── DTOs/                                          # DriverOpenResult, DriverSyncResult, DriverDiagnosticResult
├── Enums/                                         # OpenCommandState, OpenCommandSource, WhitelistAction, WhitelistChangeStatus
├── Models/                                        # OpenCommand, OpenCommandAttempt, OpenCommandFeedback, WhitelistChange
├── Support/                                       # OperatorFallbackPolicy, AcceptedOpenCommand
├── Exceptions/                                    # CooldownException (429+Retry-After), OpenNotPermittedException (403)
├── Events/                                        # OpenCommandIssued, OpenCommandCompleted, OpenFeedbackSubmitted, WhitelistResyncRequested
├── Services/                                      # DriverResolver, CooldownGuard, ExpectedCompletionEstimator,
│                                                  #   OpenCommandService (state machine), CommandDispatcher,
│                                                  #   WhitelistService, FeedbackService
├── Queries/                                       # OpenCommandQuery, WhitelistChangeQuery, DeviceStatsQuery
├── Policies/OpenCommandPolicy.php
├── Actions/                                       # OpenDevice, SubmitOpenFeedback, ResyncWhitelist
├── Jobs/                                          # DispatchOpenCommandJob ('open' queue), ExpireStaleOpenCommandsJob
└── Listeners/                                     # AddOwnerToWhitelistOnDeviceAssigned,
                                                   #   ReprogramWhitelistOnDeviceTransferred,
                                                   #   ClearWhitelistOnDeviceDecommissioned
app/Http/
├── Api/V1/Requests/Commands/                      # OpenDeviceRequest, SubmitFeedbackRequest
├── Api/V1/Controllers/Commands/OpenCommandController.php
├── Api/V1/Controllers/Devices/DeviceStatsController.php
├── Admin/V1/Controllers/Devices/AdminDeviceCommController.php
└── Resources/                                     # OpenCommandResource, WhitelistChangeResource
tests/
├── Unit/DeviceComm/OpenCommandStateTest.php
├── Feature/DeviceComm/  OpenDeviceTest · CommandDispatchTest · CommandHistoryTest · FeedbackTest ·
│                        DeviceStatsTest · WhitelistOutboxTest · ExpireStaleCommandsTest
└── Support/FakeDeviceDriver.php                    # test-only DeviceDriver double (R-ARCH-08)
```

**Touched (wiring):** `routes/api.php` (open / commands / stats / feedback), `routes/admin.php`
(admin commands / whitelist-queue / resync), `routes/console.php` (`ExpireStaleOpenCommandsJob`
every 5 min), `app/Exceptions/Contracts/DomainException.php` + `ApiExceptionRenderer.php` (a
`headers()` hook so 429 carries `Retry-After`), `app/Domain/Devices/Enums/DriverType.php`
(`confirmsActuation()` — R-GSM-03).

---

## 2. Implemented endpoints (all per openapi/v1.yaml)

| operationId | Method | Path | Guard | Notes |
|---|---|---|---|---|
| openDevice | POST | `/v1/devices/{id}/open` | auth:user | 202 OpenCommandAccepted; R-DOM-05 permission, cooldown, idempotency, `throttle:open` |
| listDeviceCommands | GET | `/v1/devices/{id}/commands` | auth:user | caller-scoped history; state/since/until filters |
| getDeviceStats | GET | `/v1/devices/{id}/stats` | auth:user | open_commands aggregation (7d/30d/90d) |
| getCommand | GET | `/v1/commands/{id}` | auth:user | terminal-state polling (WS fallback) |
| submitOpenFeedback | POST | `/v1/commands/{id}/feedback` | auth:user | 201 new / 200 replay; one per (command, user) |
| adminDeviceCommands | GET | `/admin/v1/devices/{id}/commands` | auth:admin | all users' history |
| adminWhitelistQueue | GET | `/admin/v1/devices/{id}/whitelist-queue` | auth:admin | outbox inspection; status filter |
| adminResyncWhitelist | POST | `/admin/v1/devices/{id}/whitelist/resync` | auth:admin, technical/super | 202; enqueues clear + add-all at priority 10 |

### Behaviours implemented
Open-command lifecycle state machine (R-GSM-06: queued → dispatching → dispatched/opened/failed/expired)
with idempotent transitions · **CLIP cannot claim actuation** — `opened` only when the driver confirms
*and* the driver type may confirm (R-GSM-03), surfaced as `driver_confirms_actuation` · per-(user,device)
5 s + per-device 2 s **cooldown** with a `Retry-After` hint (R-DOM-09) · idempotency on
`(user_id, idempotency_key)` · **driver fallback once** on a transient failure via the model's fallback
driver, both attempts persisted (R-GSM-04) · server-computed `expected_completion_ms` (rolling p90, per-
driver default < 10 samples — R-GSM-09) · **whitelist outbox** (R-GSM-07): ownership events + admin
resync enqueue `whitelist_changes` with a monotonic `seq`; never applied inline · device-stats
aggregation over open_commands · user actuation feedback (CRIT-06) · stale-command expiry sweep.

---

## 3. Database changes

4 new tables (DB Arch §6.1–6.3), migration set `07_device_ops`:

| Table | Highlights |
|---|---|
| `open_commands` | **RANGE-partitioned monthly** (raw SQL, 12 fwd + MAXVALUE); PK `(id, partition_key)`; **no FKs** (partitioning constraint — app-enforced); idempotency unique `(user_id, idempotency_key, partition_key)` |
| `open_command_attempts` | per-attempt history (primary + fallback); **no FK** to the partitioned parent |
| `open_command_feedback` | one per `(open_command_id, user_id)`; `user_id` FK; no FK to the partitioned parent |
| `whitelist_changes` | outbox; `seq` populated = `id` (monotonic drain key, MED-09); FKs to devices/device_users/users/admins |

**Deviations (consistent with batch 05 payment_logs):** partitioned InnoDB tables carry no foreign keys,
so the device/user/device_user/subscription FKs on `open_commands` and the `open_command_id` cascade on
attempts/feedback are app-enforced. The idempotency unique gained `partition_key` (every unique key must
include the partition column); MySQL's NULL-distinctness preserves the "WHERE idempotency_key NOT NULL"
intent and replays arrive same-month. `device_diagnostics` is **not** created here — it has no data
source without GSM (deferred with `adminDeviceDiagnostics` / `techDiagnosticsPing`).

---

## 4. Test results

```
PASS  Unit/DeviceComm/OpenCommandStateTest          (11)  terminal / success classification
PASS  Feature/DeviceComm/OpenDeviceTest             (8)   202 queued, idempotent, cooldown(429+Retry-After),
                                                          device_disabled, subscription_required, 404, 422, 401
PASS  Feature/DeviceComm/CommandDispatchTest        (6)   driver_unsupported, dispatched vs opened (R-GSM-03),
                                                          fallback once (R-GSM-04), non-transient no-fallback, via job
PASS  Feature/DeviceComm/CommandHistoryTest         (4)   caller-scoped list, state filter, getCommand 404, admin all
PASS  Feature/DeviceComm/FeedbackTest               (3)   201 then 200 replay, validation, 404 non-owner
PASS  Feature/DeviceComm/DeviceStatsTest            (3)   aggregation, zeroes, 404 non-member
PASS  Feature/DeviceComm/WhitelistOutboxTest        (6)   assigned→add, transfer→remove+add, decommission→clear,
                                                          resync (clear+add-all @ priority 10), queue list, 401
PASS  Feature/DeviceComm/ExpireStaleCommandsTest    (2)   stale→expired, terminal untouched

DeviceComm: 43 passed   ·   Full suite: 202 passed (673 assertions)   ·   ~15s (MariaDB, R-CODE-09)
```

Notable cases proving the spec-critical paths:
- **R-GSM-03:** a driver result of `opened()` on a `clip_sms` (CLIP-primary) device still terminates at
  `dispatched`; only a confirming driver (SMS) reaches `opened`.
- **R-GSM-04:** a transient `busy` on the primary driver retries once via the model's fallback driver and
  succeeds, with two `open_command_attempts` rows; a non-transient failure does not fall back.
- **No-driver reality:** with no concrete driver registered (this batch), dispatch terminates as
  `failed/driver_unsupported` — real behaviour, not a mock.
- **R-DOM-09 cooldown:** a rapid second open returns 429 with a `Retry-After` header; idempotent replays
  return the same command without re-cooling or re-dispatching.
- **R-GSM-07 outbox:** assign/transfer/decommission and admin resync only *enqueue* `whitelist_changes`
  (with monotonic `seq`); nothing touches a device.

---

## 5. Coverage summary

- **Functional coverage:** every "Implement:" item (open_commands + attempts + feedback tables, lifecycle
  state machine, cooldown, command queue, whitelist queue + sync tracking, command status queries, device
  stats aggregation, and the three Devices-event listeners) has at least one passing test, plus all
  endpoints that depend on open_commands / command history.
- **Line coverage:** not measured — no pcov/xdebug in this XAMPP build. Enable pcov and run
  `php artisan test --coverage --min=70` (R-CODE-09).

---

## 6. Design notes & boundaries (for review)

1. **Driver layer is interfaces only (R-GSM-01).** `DeviceDriver` + `DriverResolver` (with
   `OperatorFallbackPolicy`, R-GSM-02) are implemented; concrete drivers register in the container under
   `device-driver.<type>`. None are registered here, so `DriverResolver::resolveType()` returns null and
   dispatch ends `driver_unsupported`. The GSM batch binds CLIP/SMS/MQTT + the voice gateway with **no
   core change**. The `CommandDispatcher` already contains the full success/confirmation/fallback logic;
   a test-only `FakeDeviceDriver` (tests/Support, like FakeKapitalGateway) exercises those paths.
2. **The queue is real; the drain is GSM.** `openDevice` creates the queued command and pushes
   `DispatchOpenCommandJob` onto the `open` queue. The whitelist outbox accumulates `pending`
   `whitelist_changes`; the per-device `WhitelistSyncJob` drain (which calls `driver->whitelistAdd/Remove`)
   is the GSM batch. This batch owns enqueue + status tracking + inspection only.
3. **Deferred to the GSM batch (no data source without a driver):** `device_diagnostics` table,
   `adminDeviceDiagnostics`, `techDiagnosticsPing`, the diagnostics ping flow, concrete drivers, voice
   gateway selection + circuit breaker (R-GSM-05), and inbound-SMS correlation (R-GSM-10).
4. **WebSocket broadcasting is an optimisation (R-ARCH-12).** `OpenCommandAccepted.websocket_channel`
   returns `private-user.{id}`; `OpenCommandCompleted` is the event a Reverb broadcaster will consume.
   The contractual path is polling `/v1/commands/{id}` — implemented and tested. The Reverb broadcast
   wiring is deferred (optimisation, not a launch blocker per R-ARCH-12).
5. **Partitioning constraints** forced the documented FK/uniqueness adaptations (see §3) — identical to
   the batch-05 `payment_logs` treatment; app-layer integrity, recorded in the migration docblocks.
6. **`expected_completion_ms`** is the rolling p90 over recent successful opens for the device+driver,
   falling back to a per-driver constant below 10 samples (never a blind constant — R-GSM-09).
7. **Cooldown storage** uses the cache (`Cache::put/get`) — Redis SETNX-equivalent in production; the
   hard guard remains the `throttle:open` limiter (12/user, 4/device — R-SEC-16). Tests neutralise the
   limiter (as established) and exercise the cooldown directly.
8. **Whitelist burst guard (R-GSM-08)** fires on user-initiated roster adds (Roster batch); the
   system-driven listeners + admin resync here are exempt by design (resync uses priority 10 to drain
   ahead of routine changes). `WhitelistService::pendingCount()` is provided for the Roster batch to use.

---

*End of Batch 09-A Review v1.0. Stopping here — DeviceComm core complete, **not** starting GSM drivers. Awaiting review.*
