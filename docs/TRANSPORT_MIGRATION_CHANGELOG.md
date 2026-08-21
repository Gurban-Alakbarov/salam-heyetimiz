# TRANSPORT MIGRATION CHANGELOG — CLIP/GSM → UMKa/Traccar/BLE (v1.1 → v1.2)

**Date:** 2026-06-14
**Authorised by:** `FINAL_TRANSPORT_DECISION.md` (APPROVED), per `DOCUMENT_CHANGE_PLAN.md`.
**Nature:** documentation-only. **No implementation code, config, or seed was changed** (those are 09-B —
see "Deferred" below). Records every edit applied to the spec corpus in this pass.

---

## PROJECT_CONSTITUTION.md → v1.2

- Header: v1.1 → **v1.2**; status FROZEN → Active; added the **v1.2 TRANSPORT AMENDMENT** banner.
- Principle 4 ("Honesty over optimism"): CLIP-specific wording → transport-neutral; CRIT-06 marked resolved.
- **R-GSM-01**: driver enum `clip,sms,clip_sms,mqtt` → **`traccar,ble,sms`** (interface unchanged).
- **R-GSM-02**: `OperatorFallbackPolicy`/CLI validation **retired** (CRIT-01 retired).
- **R-GSM-03**: actuation confirmation now via Traccar read-back / BLE ack; `opened` reachable (CRIT-06 resolved).
- **R-GSM-04**: fallback retained; fallback driver = `sms`.
- **R-GSM-05**: voice-gateway/`VoiceGatewaySelector`/circuit-breaker **retired** → Traccar remote + SMS fallback (CRIT-03 retired).
- **R-GSM-07/08**: outbox re-scoped to device-config/BLE-credential/Traccar-sync (mechanics unchanged).
- **R-GSM-10**: telemetry/online via Traccar event-forward; inbound SMS correlation = SMS path only.
- **R-GSM-13**: perf re-baselined — BLE ≤1 s / Traccar ≤3 s / SMS ≤30 s; `gateway_capacity` → `transport_capacity`.
- **R-DOM-05**: added the **BLE local-open exception** (time-boxed entitlement + in-app enforcement + async reconciliation; remote/Traccar keeps real-time check).
- **R-SEC-04**: clarified app-enforced for the BLE path.
- **R-WF-07**: CLI-validation artefacts → **transport-validation** artefacts (`docs/phase0/transport-validation.md`).
- **Fix-Now (a)** + Phase-0 open items: CLIP gate replaced by transport-validation gate; hardware confirmed = UMKa 310 v2L.

## TECHNICAL_SPECIFICATION.md → v1.2

- Header: v1.1 → **v1.2** + amendment banner.
- **§12**: added a prominent **"§12 SUPERSEDED BY THE v1.2 TRANSPORT MODEL"** banner (authoritative table + retained/retired list). Subsections §12.1–§12.9 and the device-comm mermaid diagrams are retained as historical context, read through the banner.
- Glossary: **Whitelist** and **Driver** redefined; opening **assumptions** rewritten (UMKa/Wialon/Traccar/BLE; no on-device caller-ID whitelist).
- §3.1 NFR: open p95 re-baselined per path.
- Scope summary (device-communication row; device-model open item): CLIP → traccar/ble/sms; CLI gate → transport gate.
- FR-DEV-01 (driver type), FR-DEV-04 / FR-OWN-04 (provisioning instead of caller-ID whitelist), FR-ADM-03 (Traccar diagnostics).

## DATABASE_ARCHITECTURE.md → v1.2

- Header: v1.1 → **v1.2** (notes only — **no DDL change**).
- §2.2 `device_models`: `default_driver_type`/`fallback_open_driver` → traccar/ble/sms; `supports_*` vestigial; seed → UMKa 310 (09-B).
- §6.1 `open_commands`: `driver` value set → traccar/ble/sms (VARCHAR, no schema change).
- §6.1.1 `open_command_attempts`: `voice_gateway_id` marked **vestigial** (repurpose → `transport_ref` in 09-B).
- §6.2 `device_diagnostics`: data source → Traccar telemetry; table created in 09-B.
- §6.3 `whitelist_changes`: re-scoped to provisioning outbox (columns unchanged).

## BACKEND_ARCHITECTURE.md → v1.2

- Header: v1.1 → **v1.2** + summary.
- §3.x adapter naming + boundary examples: `ClipDriver`/`VoiceGateway` → `TraccarDriver`/`TraccarClient`.
- §13 integrations table: `VoiceGateway` row → **`TraccarClient`**; `DeviceDriver` impls → `TraccarDriver`/`SmsDriver`/`BleProvisioningDriver`/`FakeDeviceDriver`; `IntegrationsServiceProvider` list updated.
- §14.6 DeviceComm: full **v1.2 transport-pivot** rewrite of the driver-layer description (retire voice gateway / `VoiceGatewaySelector` / `OperatorFallbackPolicy`; add Traccar + BLE; keep interface, resolver, pipeline, outbox, fallback).
- §6.x queues: `device-comm` queue profile CLIP/SMS → Traccar/BLE/SMS.

## docs/openapi/v1.yaml → 1.2.0

- `info.version` `1.1.0` → **`1.2.0`**.
- `driver` / `driver_type` / `default_driver_type` enum (5 sites) `clip,sms,clip_sms,mqtt` → **`traccar,ble,sms`**.
- `OpenCommandAccepted.driver_confirms_actuation` description → Traccar/BLE (CRIT-06 resolved).
- `submitOpenFeedback` description → transport-neutral.
- **Validator: green** (81 schemas, 96 refs resolve, 100 operationIds, 26 tags).

## CHANGELOG.md / BATCH_09A_REVIEW.md

- `CHANGELOG.md`: added the **v1.2 — Transport Amendment** entry.
- `BATCH_09A_REVIEW.md`: added a **v1.2 forward note** (09-A core stands valid; deferred items now map to Traccar/BLE/SMS).

---

## Deferred to batch 09-B (NOT changed in this documentation pass)

| Artifact | Why deferred |
|---|---|
| `app/Domain/Devices/Enums/DriverType.php` | Breaking enum change; must be coordinated with the OpenAPI enum + a data migration of existing `clip_sms` rows — executed as the **first 09-B step** (`DOCUMENT_CHANGE_PLAN.md` §7.3). |
| `config/domain/device_comm.php` | Implementation config (drivers list, `operator_default_drivers`, `voice` block, transient codes). |
| `config/integrations/voice.php` | Removed in 09-B (voice gateway retired). |
| `database/seeders/Lookups/DeviceModelSeeder.php` | RTU5024 → UMKa 310 v2L. |
| 09-A test assertions referencing `clip_sms` | Updated alongside the enum change in 09-B. |

*Documentation migration complete. Implementation (config/enum/seed + 09-B) awaits the readiness sign-off
in `BATCH_09B_READINESS.md`.*
