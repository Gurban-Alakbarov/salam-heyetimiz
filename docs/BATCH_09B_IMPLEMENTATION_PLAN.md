# BATCH 09-B — Implementation Plan (Traccar + SMS, no BLE)

**Date:** 2026-06-14
**Supersedes:** `BATCH_09B_SCOPE.md` (BLE removed per `FINAL_PHASE0_VERDICT.md`).
**Premise:** Traccar is the **primary** transport for **both local and remote** opens; SMS is the
**fallback**; **BLE is deferred** (off MVP). Built against a `FakeTraccarClient` (proven 05–09A pattern);
the live `OUTPUT0` command string is finalised by Phase-0 test T1. **No CLIP, no voice gateway.**

---

## 1. Scope (the redefined six)

1. **`TraccarDriver`** (`device-driver.traccar`) — `open()` sends a Traccar `custom` text command
   (`OUTPUT0=1`, or the `cmdout.p` trigger when confirmed) → returns `dispatched`; `whitelistAdd/Remove`,
   `diagnose`, `supports` per the contract.
2. **Traccar webhook ingestion** — receive Traccar event-forward (positions, I/O/output state, online
   status, command results) at a backend webhook (HMAC/shared-secret), update device online status,
   `device_diagnostics`, and **confirm opens** (`dispatched → opened`).
3. **Traccar device mapping** — table + service mapping our `device_id` ↔ Traccar `id`/`uniqueId` (IMEI);
   provisioning helper to register a device in Traccar.
4. **Actuation confirmation** — derive `opened` from the OUT0-bit change in ingested telemetry (tune
   `STATMASK`); fall back to terminal `dispatched` when no confirmation channel (R-GSM-03).
5. **`SmsDriver` fallback** (`device-driver.sms`) — `open()` via SMS provider (`AUTH`+`OUTPUT0=1`);
   used by `CommandDispatcher` on a transient Traccar failure / offline device (R-GSM-04); inbound-SMS
   HMAC correlation (SMS path only, R-GSM-10).
6. **Device diagnostics** — create `device_diagnostics` (DB Arch §6.2) populated from Traccar telemetry;
   implement `adminDeviceDiagnostics` (history). `techDiagnosticsPing` may follow once ingestion is proven.

**Out of scope:** `BleProvisioningDriver`, BLE entitlements/reconciliation, R-DOM-05 BLE exception, voice
gateway, CLIP driver, MQTT.

## 2. Reuse from 09-A (unchanged)

`DeviceDriver` interface, `DriverResolver` (`device-driver.<type>`), `OpenCommandService` state machine,
`CommandDispatcher` (single-fallback logic), `OpenCommandQuery`, `DeviceStatsQuery`, `OpenCommandPolicy`,
`CooldownGuard`, `ExpectedCompletionEstimator`, idempotency, `DispatchOpenCommandJob`,
`ExpireStaleOpenCommandsJob`, the whitelist outbox (`WhitelistService` + listeners + endpoints),
`OpenCommandIssued/Completed` events, feedback flow, `FakeDeviceDriver`. **The seam absorbs the new drivers
with no core change.**

## 3. Build order

1. **First commit (breaking, coordinated — SB4/B3):** `DriverType` enum `clip/sms/clip_sms/mqtt` →
   `traccar/sms` (`ble` reserved/deferred); update `confirmsActuation()` (traccar ✓, sms per capability);
   update the OpenAPI enum (already `traccar/ble/sms` — narrow to match), the 09-A tests asserting
   `clip_sms`, `config/domain/device_comm.php`, and `DeviceModelSeeder` → **UMKa 310 v2L** (GLONASSSoft).
   Keep the suite green.
2. **Schema:** `device_diagnostics` migration; `traccar_devices` (device-mapping) migration; repurpose
   `open_command_attempts.voice_gateway_id` → `transport_ref` (optional migration).
3. **Integration adapter:** `TraccarClient` contract + `HttpTraccarClient` (REST: command send, device CRUD)
   + `FakeTraccarClient` (test double); `config/integrations/traccar.php`; bind in `IntegrationsServiceProvider`
   (fake in testing, http otherwise) — mirror the Kapital/OTP binding.
4. **Drivers:** `TraccarDriver`, `SmsDriver` (uses the existing SMS provider seam); register under
   `device-driver.traccar` / `device-driver.sms`. `CommandDispatcher` now resolves real drivers.
5. **Ingestion:** Traccar event-forward webhook controller + service → online status, `device_diagnostics`,
   open confirmation (`opened`).
6. **Admin/diagnostics endpoints:** `adminDeviceDiagnostics`; provisioning (register device in Traccar).
7. **Tests:** `TraccarDriver` against `FakeTraccarClient` (dispatched/opened/failed + fallback to SMS);
   webhook ingestion → confirmation; device-mapping; diagnostics; SMS fallback; retain all 09-A tests.

## 4. Acceptance criteria

- Open via Traccar reaches `dispatched`, then `opened` on confirmed actuation; SMS fallback works when the
  device is offline; webhook ingestion populates diagnostics + online status; `WhitelistSyncJob` drains the
  outbox via the resolved driver; OpenAPI validates green; full suite green (new + retained 09-A).
- **Production go-live additionally requires** HB1 + HB2 cleared (Phase-0 T1–T3 on real hardware) — see
  `PHASE0_BLOCKERS.md`.

---

## Recommended Next Prompt (send verbatim to start 09-B)

> Proceed with Batch 09-B: DeviceComm Transport (Traccar + SMS).
>
> Use all approved documents as source of truth, especially FINAL_PHASE0_VERDICT.md,
> BATCH_09B_IMPLEMENTATION_PLAN.md, PHASE0_BLOCKERS.md, UMKA_COMMAND_REFERENCE.md, and
> TRACCAR_INTEGRATION_STRATEGY.md.
>
> Implement only the DeviceComm transport layer over the existing 09-A seam. BLE is deferred — do NOT
> implement BleProvisioningDriver, BLE entitlements, or the R-DOM-05 BLE exception. Do NOT implement CLIP,
> voice gateway, or MQTT.
>
> First (breaking, coordinated, single commit): change the DriverType enum from clip/sms/clip_sms/mqtt to
> traccar/sms (reserve ble), update confirmsActuation(), the OpenAPI driver enum, the 09-A tests that assert
> clip_sms, config/domain/device_comm.php, and the DeviceModelSeeder (King Pigeon RTU5024 → GLONASSSoft
> UMKa 310 v2L). Keep the full test suite green.
>
> Then implement:
> 1. Migrations — device_diagnostics (DB Arch §6.2); traccar_devices device-mapping (our device_id ↔ Traccar
>    uniqueId/IMEI). No change to the partitioned open_commands.
> 2. TraccarClient contract + HttpTraccarClient (REST: send custom command, device CRUD) + FakeTraccarClient
>    test double; config/integrations/traccar.php; bind in IntegrationsServiceProvider (fake in testing, http
>    otherwise). Remove config/integrations/voice.php.
> 3. TraccarDriver (device-driver.traccar): open() = custom command OUTPUT0=1 (cmdout.p trigger when
>    confirmed) → dispatched; whitelistAdd/Remove, diagnose, supports.
> 4. SmsDriver (device-driver.sms): open() via the SMS provider (AUTH + OUTPUT0=1) as the transient/offline
>    fallback; inbound-SMS HMAC correlation.
> 5. Traccar event-forward webhook (HMAC) → ingestion service: device online status, device_diagnostics,
>    and open confirmation (dispatched → opened from the OUT0 bit).
> 6. Device-mapping service + Traccar device registration/provisioning.
> 7. adminDeviceDiagnostics endpoint (history). WhitelistSyncJob drain via the resolved driver.
>
> Requirements: Follow OpenAPI exactly, follow the Constitution exactly, no TODO placeholders, no mock
> implementations (FakeTraccarClient is a test double, like FakeKapitalGateway). Reuse the 09-A DeviceDriver
> seam, state machine, cooldown, idempotency, and whitelist outbox unchanged.
>
> After implementation: 1) Run migrations 2) Run tests 3) Run route:list 4) Run OpenAPI validation
> 5) Create BATCH_09B_REVIEW.md.
>
> Stop after Batch 09-B. Do not begin BLE or Phase-0 hardware testing. Wait for review.
