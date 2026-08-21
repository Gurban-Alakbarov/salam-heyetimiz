# BATCH 09-B SCOPE — Transport Drivers (Traccar + BLE + SMS)

**Status:** Scope — 2026-06-14. Re-scopes the former "GSM drivers (CLIP/SMS/voice)" batch under
`FINAL_TRANSPORT_DECISION.md`. **No code yet** — this defines what 09-B builds once approved.
**Prerequisite:** the doc revisions in `DOCUMENT_CHANGE_PLAN.md` (at minimum the driver-enum change)
land first, since the enum change is breaking and coordinated.

---

## 1. Goal

Plug the **concrete transports** into the existing `DeviceDriver` seam from batch 09-A so that the
barrier-opening flow (Open Command → Device Command → `cmdout.p` → 1-second relay pulse → barrier opens)
works end-to-end over: **BLE** (local primary), **Traccar** (remote primary + telemetry + confirmation),
**SMS** (emergency fallback). The control plane built in 09-A is reused unchanged.

## 2. What stays unchanged (existing code that needs no edit)

These are correct regardless of transport and are **not touched** by 09-B except where noted:

- **Whole control plane:** Auth (07), Payments (05), Subscriptions (06), Devices (08) — including the
  device-sale `DeviceAssigner`, transfer, status transitions, and the §13.9 `SubscriptionStatusQuery`
  (R-DOM-05 permission) that gates remote opens.
- **The error envelope, idempotency, cursor pagination, rate-limiter buckets** (incl. `throttle:open`).
- **The `open_commands` schema and partitioning** (no DDL change).

## 3. What of Batch 09-A remains valid (the seam pays off)

**Retained in full — these are the reason 09-A was "driver interfaces only":**
- `DeviceDriver` **interface** (`open / whitelistAdd / whitelistRemove / diagnose / supports`) — the
  new drivers implement exactly this.
- `DriverResolver` (+ container lookup `device-driver.<type>`) — 09-B just **registers** concrete
  drivers; resolution logic stays.
- `OpenCommandService` **state machine** (queued → dispatching → dispatched/opened/failed/expired),
  `open_command_attempts`, `CommandDispatcher` (incl. the single-fallback path — R-GSM-04),
  `OpenCommandQuery`, `DeviceStatsQuery`, `OpenCommandPolicy`.
- `CooldownGuard`, `ExpectedCompletionEstimator`, idempotency, `DispatchOpenCommandJob`,
  `ExpireStaleOpenCommandsJob`, `OpenDevice`/`SubmitOpenFeedback`/`ResyncWhitelist` actions,
  `OpenCommandController` / `AdminDeviceCommController` / `DeviceStatsController`.
- The **whitelist outbox** (`whitelist_changes` + `WhitelistService` + the three device-event listeners)
  — re-purposed in meaning (config/credential/Traccar sync) but the table, enqueue, tracking, and queue
  endpoint stay.
- `OpenCommandCompleted` event (the Reverb broadcast hook), `OpenCommandIssued`, feedback flow.
- `Tests\Support\FakeDeviceDriver` — already exercises the dispatcher; 09-B adds real drivers beside it.

**Minor changes inside 09-A files (coordinated, breaking — done at the start of 09-B):**
- `DriverType` enum values `clip/clip_sms/mqtt` → `traccar/ble/sms` (+ `confirmsActuation()` updated:
  `traccar` and `ble` confirm; `sms` per device capability). Touches the enum, the OpenAPI enum, and a
  dev/test data migration of existing `clip_sms` rows.

## 4. What Batch 09-B becomes — in scope

1. **Traccar integration (remote primary):**
   - `TraccarClient` adapter (`config/integrations/traccar.php`: base URL, token, device mapping,
     `cmdout.p` command spec, event-forward secret).
   - `TraccarDriver implements DeviceDriver` — `open()` sends the custom/output command that runs
     `cmdout.p`; reads command ack / output-state for confirmation. Bound as `device-driver.traccar`.
   - **Event-forward ingestion** (Traccar → backend webhook): positions, I/O/output state, online/offline,
     command results → updates `device_diagnostics`, device online status, and confirms `open_commands`
     (`dispatched` → `opened`).
   - A **device-mapping** model/table (our device id ↔ Traccar device + uniqueId/IMEI).
   - Device provisioning: point UMKa units at our Traccar (server/port/protocol = Wialon Combine) via
     configuration software / SMS config (ops runbook, not necessarily code).
2. **BLE provisioning + local-open (local primary):**
   - Backend issuance of **time-boxed access credentials/entitlements** to the app (new table + endpoint),
     authenticated/rolling, scoped to (user, device), expiring per policy.
   - **Open reconciliation endpoint**: the app reports a completed BLE open after the fact → recorded as
     an `open_commands` row (source/automation), audited, entitlement-checked.
   - The whitelist outbox carries **BLE credential provisioning** to the device where applicable.
   - (The on-device BLE script + key exchange specifics are validated in Phase 0.)
3. **SMS fallback driver:**
   - `SmsDriver implements DeviceDriver` — `open()` sends the device SMS command (`device_models.sms_open_command`)
     via the SMS provider; used only when the device is offline from Traccar. Bound as `device-driver.sms`.
   - Inbound-SMS HMAC correlation to the originating `open_commands` row (R-GSM-10, SMS path only).
4. **`WhitelistSyncJob` drain (now real):** per-device serialised drain of `whitelist_changes` in
   (priority ASC, seq ASC) order, calling the driver's `whitelistAdd/whitelistRemove` (Traccar/SMS) or
   pushing BLE credentials, with retry/backoff (max 5). 09-A only enqueues; 09-B drains.
5. **device_diagnostics table + endpoints:** create the table (DB Arch §6.2); implement
   `adminDeviceDiagnostics` (history list) and `techDiagnosticsPing` (synchronous probe via Traccar).
6. **Reverb broadcast (optimisation):** broadcast `OpenCommandCompleted` on `private-user.{id}`; polling
   `/v1/commands/{id}` remains the contractual fallback (R-ARCH-12).

## 5. Out of scope / retired (09-B does NOT build these)

- CLIP driver, `VoiceGateway`, `VoiceGatewaySelector`, circuit breaker (CRIT-03 retired).
- `OperatorFallbackPolicy` per-operator caller-ID downgrade + CLI validation (CRIT-01 retired) — remove
  `config/integrations/voice.php` and the `operator_default_drivers`/`voice` blocks.
- On-device phone-number whitelist *for caller-ID opening*.
- MQTT driver (remains future/optional, not built).

## 6. Phase-0 validation gates (replace the retired CLIP gate G1)

1. **Traccar → `cmdout.p`:** confirm a Traccar custom/output command triggers the relay pulse on a real
   UMKa 310 over a live Wialon session; capture the exact command spec (per UMKa manual / `dop` docs).
2. **Latency:** measure tap→pulse for Traccar (target ~1–3 s online) and BLE (sub-second).
3. **BLE security model:** how the device authorises a BLE trigger → defines credential format and what
   the provisioning outbox pushes; verify rolling/authenticated (no static keys; relay-attack resistant).
4. **Output-state read-back:** confirm actuation is observable via Traccar (→ `opened` is real).
5. **SMS fallback:** command syntax, cost, latency, inbound correlation.
6. **Session durability:** how reliably UMKa holds a Wialon session in deployment coverage.

## 7. Dependencies & risks

- **New mandatory infra:** self-hosted Traccar (HA/monitoring/backup is an ops concern). Backend gains a
  hard dependency on Traccar for the remote path (SMS fallback covers Traccar outage for opens).
- **Breaking enum change** must be coordinated (code + OpenAPI + data) — see `DOCUMENT_CHANGE_PLAN.md` §7.
- **BLE is the highest-unknown area** (per-device key provisioning, OS BLE quirks, security) — Phase-0
  must de-risk it before committing the local-open UX as primary.
- Traccar's support for the precise UMKa output/custom command is a Phase-0 assumption, not yet proven.

## 8. Acceptance criteria (for 09-B completion)

- Remote open via Traccar reaches `dispatched`, then `opened` on output confirmation; SMS fallback works
  when offline; BLE entitlement issuance + reconciliation audited; `WhitelistSyncJob` drains the outbox;
  `device_diagnostics` populated from Traccar; all endpoints green against OpenAPI; full test suite green
  (incl. a real `TraccarDriver` test against a faked Traccar client, plus the retained 09-A tests).

---

*Scope only. No code generated. Execution awaits owner approval of `FINAL_TRANSPORT_DECISION.md` and the
driver-enum revision in `DOCUMENT_CHANGE_PLAN.md`.*
