# DOCUMENT CHANGE PLAN — CLIP/GSM → UMKa/Traccar/BLE

**Status:** Plan — 2026-06-14. Enumerates every specification, config, and seed change required to
realise `FINAL_TRANSPORT_DECISION.md`. **This is the plan, not the execution** — edits are made only
after the owner's "proceed".
**Versioning:** the affected v1.1 documents are promoted to **v1.2**; each gets a CHANGELOG entry
("Transport reassessment — UMKa 310 / Traccar / BLE; CLIP retired") referencing
`FINAL_TRANSPORT_DECISION.md`. The decision itself does not re-open any other v1.1 decision.

---

## 0. Cross-cutting concept changes

**Retired concepts** (remove or mark "retired — see Final Transport Decision"): CLIP driver, caller-ID
opening, on-device phone-number whitelist *for opening*, voice gateway, `VoiceGatewaySelector` +
circuit breaker, per-operator CLI validation, `gateway_unavailable`/`gateway_capacity` failure modes.

**New concepts** (introduce): Traccar as mandatory infrastructure; driver taxonomy `traccar/ble/sms`;
BLE local-open with time-boxed entitlement + async reconciliation; Traccar telemetry/event ingestion;
Wialon IPS/Combine as the device wire protocol (operated by Traccar, not us).

**Risk register:** CRIT-01 and CRIT-03 move to **Retired/N-A**; CRIT-06 moves to **Resolved**.

---

## 1. PROJECT_CONSTITUTION.md → v1.2

| Rule / item | Change |
|---|---|
| **R-GSM-01** | Replace driver enum `clip, sms, clip_sms, mqtt` → **`traccar, ble, sms`** (mqtt optional/future). Keep the `DeviceDriver` interface verbatim — it is unchanged and vindicated. |
| **R-GSM-02** (OperatorFallbackPolicy / CRIT-01) | **Retire.** Caller-ID/CLI validation no longer applies. Replace with a one-line note: driver resolution is `device.driver_type` (traccar primary; ble local; sms fallback). |
| **R-GSM-03** (CLIP cannot confirm / CRIT-06) | **Amend:** rationale changes — confirmation now comes from Traccar output-state read-back / BLE ack. `driver_confirms_actuation` may be `true`; `opened` reachable. CRIT-06 → Resolved. |
| **R-GSM-04** (fallback, HIGH-03) | **Keep** the mechanism; fallback driver is now `sms` (offline) rather than CLIP↔SMS. |
| **R-GSM-05** (voice gateway, CRIT-03) | **Retire** entirely. CRIT-03 → N-A. |
| **R-GSM-06** (states) | **Keep verbatim** (queued…expired). |
| **R-GSM-07 / R-GSM-08** (whitelist outbox + burst guard) | **Re-scope:** the outbox becomes a **device-config / BLE-credential provisioning + Traccar-sync outbox**, not a phone-number-for-CLIP list. Burst guard logic retained. |
| **R-GSM-09** (expected_completion_ms) | **Keep**; re-baseline defaults for traccar/ble/sms. |
| **R-GSM-10** (diagnostics 6 h + inbound SMS HMAC) | **Amend:** telemetry/online-status now arrives via Traccar event-forward; inbound-SMS correlation kept only for the SMS fallback path. |
| **R-GSM-13** (perf ≤5 s CLIP / ≤15 s SMS) | **Re-baseline:** BLE sub-second (local); Traccar ~1–3 s (remote, live session); SMS fallback ≤ ~30 s. |
| **R-DOM-05** (open-permission, real-time) | **Amend:** add the **BLE local-open exception** — time-boxed entitlement + in-app biometric/entitlement + async reconciliation; the remote/Traccar path keeps the real-time check. |
| **R-SEC-04** (biometric before every open) | **Keep**; clarify it is app-enforced for the BLE path. |
| **R-WF-07** + Fix-Now (a) CLIP Phase-0 gate, (d) "Göndərildi" copy | Fix-Now (a) **retired** (no CLIP gate); replace with Traccar/BLE Phase-0 gates. (d) copy logic retained but driven by `driver_confirms_actuation`. |
| **Phase-0 gates G1–G8** | **Replace G1** (CLIP per-operator) with the new transport gates (see `BATCH_09B_SCOPE.md` §6). |

## 2. TECHNICAL_SPECIFICATION.md → v1.2

| Section | Change |
|---|---|
| §0 scope / open items (≈L44, L57) | "Modular GSM driver layer — CLIP/SMS" → Traccar/BLE/SMS. Remove "per-operator CLI validation is a Phase-0 gate". |
| Glossary (≈L124, L127, L133–135) | Redefine **Whitelist** (no longer phone numbers on the device for opening → BLE-credential/config provisioning). Redefine **Driver** (traccar/ble/sms). Drop assumptions "SIM with voice for CLIP" and "hardware controller stores a whitelist of numbers". Add UMKa 310 + Traccar + BLE assumptions. |
| FR-DEV-01 | Device registration `driver_type` enum → traccar/ble/sms. |
| FR-DEV-04 / FR-DEV-05 / FR-OWN-04 | "whitelist sync to device (numbers)" → "provision/de-provision access (BLE credential and/or Traccar authorisation)". |
| FR-OPEN-03 / FR-OPEN-07 | Driver returns dispatched/opened/failed — keep; `opened` now reachable via Traccar/BLE confirmation. |
| FR-OPEN-08 / FR-OPEN-09 | Fallback + expected_completion_ms — keep; update driver names. |
| FR-ADM-03 | "force whitelist resync" → "force Traccar/whitelist resync"; diagnostics now from Traccar. |
| §3.1 NFR perf (≈L259) | Re-baseline open p95 per path (BLE/Traccar/SMS). |
| **§12 Device Communication Design** (whole section) | Largest rewrite. Replace the CLIP/SMS/voice-gateway driver bus, §12.6.1 (CLI validation), §12.6.2/§12.10 (voice gateway), §12.7 (CLIP↔SMS fallback) with: Traccar integration (REST command + event forward), BLE provisioning + local-open flow, SMS fallback. Keep §12 driver-interface framing + the open-command pipeline. |
| Sequence/architecture diagrams (≈L437–598) | Replace "Driver (CLIP/SMS)", "CLIP driver VoIP/GSM modem", "SMS driver gateway/modem", whitelist-programs-SIM flows with Traccar/BLE/SMS equivalents. |
| §11.5 / S-12 (CLIP "opened" copy) | Keep the dispatched/opened copy rule; driven by `driver_confirms_actuation`. |

## 3. DATABASE_ARCHITECTURE.md → v1.2

| Section | Change |
|---|---|
| §2.2 `device_models` | Capability columns stay; reinterpret for UMKa (supports_* / `default_driver_type` / `fallback_open_driver` / `sms_open_command`). Driver enum values → traccar/ble/sms (column comments). **No DDL change required** (enums already created; values are a doc + future-migration concern — see §7). |
| §6.1 `open_commands` | `driver` column comment → traccar/ble/sms. **No schema change.** |
| §6.1.1 `open_command_attempts` | `voice_gateway_id` column is now **vestigial** (no voice gateway). Mark deprecated/repurpose as `transport_ref` (e.g. Traccar command id) in a future migration; **not** dropped in 09-A (keep stable). |
| §6.1.2 `open_command_feedback` | **Unchanged.** |
| §6.2 `device_diagnostics` | **Unchanged shape**; data source becomes Traccar telemetry forward (was driver ping). Table is created in 09-B. |
| §6.3 `whitelist_changes` | Reinterpret as device-config/BLE-credential/Traccar-sync outbox; columns adequate. **No schema change.** |
| New (09-B) | A table to hold **BLE entitlements / device-access credentials** (time-boxed) and a **Traccar device mapping** (our device id ↔ Traccar device id / unique id). Specify in DB Arch §6.x in 09-B. |

## 4. OPENAPI (docs/openapi/v1.yaml) → 1.2.0

| Location | Change |
|---|---|
| `driver` enum (5 sites: `OpenCommand.driver` ≈L3029; `DeviceTechRegister.driver_type` ≈L3479; `DeviceAdmin.driver_type` ≈L3495; `DeviceAdminUpdate.driver_type` ≈L3528; `DeviceModel.default_driver_type` ≈L3608) | `clip, sms, clip_sms, mqtt` → **`traccar, ble, sms`**. |
| `OpenCommandAccepted` | Keep `driver_confirms_actuation`, `expected_completion_ms`, `websocket_channel`. No structural change. |
| Diagnostics endpoints (`adminDeviceDiagnostics`, `techDiagnosticsPing`) + `DeviceDiagnostic` | Keep; clarify source (Traccar ping/telemetry). `source` enum keeps `scheduled_ping/open_dispatch/admin_ping/device_initiated`. Implemented in 09-B. |
| Whitelist (`adminWhitelistQueue`, `adminResyncWhitelist`, `WhitelistChange`) | Keep; descriptions reflect config/credential provisioning + Traccar sync rather than CLIP numbers. |
| **New (consider)** | BLE entitlement issuance/refresh endpoint(s) for the app (e.g. `getDeviceAccessCredential`), and an open-reconciliation endpoint for after-the-fact BLE open reporting. To be specified in 09-B. |
| Validator | `php docs/openapi/validate.php` must stay green after each edit (operationIds/refs unchanged unless new endpoints added). |

## 5. BACKEND_ARCHITECTURE.md → v1.2

| Section | Change |
|---|---|
| §13 integrations table (≈L810–820) | Remove `VoiceGateway`/`KannelVoiceGateway`/`FakeVoiceGateway`. `DeviceDriver` impls `ClipDriver/SmsDriver/HybridDriver/MqttDriver` → **`TraccarDriver`, `BleProvisioningDriver`(or local-open coordinator), `SmsDriver`** (+ `FakeDeviceDriver` test double). Add a `TraccarClient` integration adapter. |
| §14.6 DeviceComm (≈L950–973) | Remove `VoiceGatewaySelector` (CRIT-03), `VoiceGateway`, CLIP fallback wording, `OperatorFallbackPolicy` (CRIT-01). Keep `DeviceDriver` interface, `DriverResolver`, `DispatchOpenCommand`, `ApplyWhitelistChange` (→ Traccar/credential sync). Add Traccar command + event-forward ingestion, BLE entitlement issuance. |
| §6.x queues (≈L657) | `device-comm` queue: drop "CLIP=4/SMS=8 per-driver" → Traccar/BLE/SMS worker profile. |
| Events (≈L612) | `OpenCommandDispatched` etc. unchanged; add Traccar-ingest + BLE-reconcile events as needed. |

## 6. DeviceComm documentation (code-level docblocks + config + seed)

| Artifact | Change (executed in 09-B, listed here for completeness) |
|---|---|
| `config/domain/device_comm.php` | Replace `drivers` list, `operator_default_drivers`, the entire `voice` block, transient/non-transient codes (Traccar/SMS codes). Add Traccar + BLE config. |
| `config/integrations/voice.php` | **Remove** (no voice gateway). |
| Add `config/integrations/traccar.php` | Base URL, auth token, command mapping for `cmdout.p`, event-forward secret. |
| `database/seeders/Lookups/DeviceModelSeeder.php` | Add/replace with **UMKa 310 v2L** (vendor GLONASSSoft) and appropriate driver defaults; retire/keep RTU5024 as inactive reference. |
| `App\Domain\Devices\Enums\DriverType` | Enum values → traccar/ble/sms (requires a coordinated code + OpenAPI + DB change — sequenced in 09-B). |
| Batch review docs (`BATCH_09A_REVIEW.md`) | Append a forward note pointing to this decision (the 09-A "deferred to GSM batch" items now map to Traccar/BLE). |

## 7. Sequencing & safety

1. **Owner approves** `FINAL_TRANSPORT_DECISION.md`.
2. Revise docs in this order: PROJECT_CONSTITUTION → TECHNICAL_SPECIFICATION §12 → BACKEND_ARCHITECTURE
   §13/§14.6 → OPENAPI enums → DATABASE_ARCHITECTURE notes → DeviceComm docblocks. Each bumps to v1.2 with
   a CHANGELOG line.
3. The `DriverType` enum value change is **breaking** for stored `driver` snapshots and the OpenAPI enum;
   it must be a single coordinated change (enum + OpenAPI + a data migration of existing `clip_sms` rows
   in dev/test) executed at the **start of 09-B**, not piecemeal.
4. **Batch 09-A code is not edited by the doc revision** — it remains valid (see `BATCH_09B_SCOPE.md` §3).

---

*Change plan only. No documents are edited and no code is generated until the owner says "proceed".*
