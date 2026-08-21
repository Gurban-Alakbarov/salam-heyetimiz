# Batch 09-B Review — DeviceComm Transport (Traccar + SMS)

**Date:** 2026-06-14
**Scope:** DeviceComm transport layer over the existing 09-A control-plane seam (R-GSM-01/03/04/06/07).
**Spec basis:** FINAL_PHASE0_VERDICT.md, BATCH_09B_IMPLEMENTATION_PLAN.md, PHASE0_BLOCKERS.md,
UMKA_COMMAND_REFERENCE.md, TRACCAR_INTEGRATION_STRATEGY.md, and the v1.2 specs (PROJECT_CONSTITUTION,
TECHNICAL_SPECIFICATION, DATABASE_ARCHITECTURE §6.2, BACKEND_ARCHITECTURE §14.6, openapi/v1.yaml).

**Result:** ✅ Complete. Full suite **225 passed (743 assertions)**. OpenAPI structurally valid.
BLE remains deferred (no code). 09-A control plane unchanged.

---

## 0. Pre-code consistency check

Re-read the affected specs and verified mutual consistency before writing code:

| Source | Driver taxonomy | Confirms actuation | Result |
|---|---|---|---|
| PROJECT_CONSTITUTION R-GSM-01 | traccar (primary), sms (fallback), ble (reserved/deferred) | Traccar yes; SMS no | ✅ |
| R-DOM-05 | BLE exception **withdrawn**; all opens are real-time | — | ✅ |
| openapi/v1.yaml (driver enum, 5 sites) | `[traccar, ble, sms]` | — | ✅ |
| BATCH_09B_IMPLEMENTATION_PLAN | TraccarDriver/SmsDriver/ingestion/mapping/diagnostics | — | ✅ |
| 09-A `CommandDispatcher::finalize()` | `opened` only when `actuated && type->confirmsActuation()` | — | ✅ matches |

Implementation checklist (all done): (1) breaking enum + coordinated edits in one commit-equivalent change;
(2) migrations device_diagnostics + traccar_devices; (3) TraccarClient contract + Http/Fake + config + binding;
(4) TraccarDriver; (5) SmsDriver; (6) Traccar event-forward webhook → ingestion (online/diagnostics/confirmation);
(7) device-mapping service + registration; (8) adminDeviceDiagnostics endpoint; (9) WhitelistSyncJob drain.

---

## 1. Files created

### Domain — enums / DTOs / contracts
- `app/Domain/DeviceComm/Enums/DiagnosticSource.php` — scheduled_ping/open_dispatch/admin_ping/device_initiated.
- `app/Domain/DeviceComm/DTOs/TraccarCommandResult.php` — `sent()`/`queued()`/`failed()`.
- `app/Domain/DeviceComm/Contracts/TraccarClient.php` — `registerDevice()`, `sendCommand()`.
- `app/Domain/DeviceComm/Contracts/DeviceSmsGateway.php` — `sendCommand(phone, command)`.

### Domain — adapters (vendor-replaceable, R-ARCH-08)
- `app/Domain/DeviceComm/Adapters/HttpTraccarClient.php` — Traccar REST (`POST /api/devices`, `POST /api/commands/send` type `custom`).
- `app/Domain/DeviceComm/Adapters/FakeTraccarClient.php` — in-memory test double (records sends/registrations, `willReturn()`).
- `app/Domain/DeviceComm/Adapters/HttpDeviceSmsGateway.php` — AZ SMS provider HTTP.
- `app/Domain/DeviceComm/Adapters/FakeDeviceSmsGateway.php` — in-memory test double (records `sent[phone]`).

### Domain — drivers (DeviceDriver implementations)
- `app/Domain/DeviceComm/Drivers/TraccarDriver.php` — `device-driver.traccar`; `open()` → `OUTPUT0=1` via custom command → `dispatched`.
- `app/Domain/DeviceComm/Drivers/SmsDriver.php` — `device-driver.sms`; SMS fallback → `dispatched`.

### Domain — services / jobs / models
- `app/Domain/DeviceComm/Services/TraccarDeviceMapper.php` — register + map devices to Traccar identity (idempotent).
- `app/Domain/DeviceComm/Services/TraccarIngestionService.php` — event-forward ingestion (diagnostics, online, actuation confirm).
- `app/Domain/DeviceComm/Jobs/WhitelistSyncJob.php` — drains the whitelist outbox via the resolved driver.
- `app/Domain/DeviceComm/Models/DeviceDiagnostic.php` — partitioned diagnostics row (timestamps off).
- `app/Domain/DeviceComm/Models/TraccarDevice.php` — device ↔ Traccar mapping.
- `app/Domain/DeviceComm/Queries/DeviceDiagnosticQuery.php` — cursor-paginated history (newest first).

### HTTP
- `app/Http/Webhooks/Controllers/TraccarForwardController.php` — `POST /v1/traccar/forward` (shared-secret token gate → ingest).
- `app/Http/Resources/DeviceDiagnosticResource.php` — maps to `components/schemas/DeviceDiagnostic`.

### Config & migrations
- `config/integrations/traccar.php` — driver fake|http, base_url, api_token, forward_token, output_attribute, confirm window.
- `database/migrations/07_device_ops/2026_06_13_070005_create_device_diagnostics_table.php` — partitioned (DB Arch §6.2).
- `database/migrations/07_device_ops/2026_06_13_070006_create_traccar_devices_table.php` — mapping table.

### Tests (new, 09-B)
- `tests/Feature/DeviceComm/TraccarDriverTest.php` (4)
- `tests/Feature/DeviceComm/TraccarIngestionTest.php` (4)
- `tests/Feature/DeviceComm/TraccarDeviceMapperTest.php` (3)
- `tests/Feature/DeviceComm/TraccarForwardWebhookTest.php` (4)
- `tests/Feature/DeviceComm/SmsFallbackTest.php` (2)
- `tests/Feature/DeviceComm/AdminDeviceDiagnosticsTest.php` (2)
- `tests/Feature/DeviceComm/WhitelistSyncJobTest.php` (4)

---

## 2. Files modified

### Breaking enum change (coordinated, single change-set)
- `app/Domain/Devices/Enums/DriverType.php` — cases `Traccar`/`Sms`/`Ble`; `confirmsActuation()` → Traccar true, Sms/Ble false.
- `database/migrations/01_identity_lookups/..._create_device_models_table.php` — `default_driver_type`/`fallback_open_driver` enum `[traccar,ble,sms]` default `traccar`.
- `database/migrations/04_devices/..._create_devices_table.php` — `driver_type` enum `[traccar,ble,sms]` default `traccar`.
- `database/seeders/Lookups/DeviceModelSeeder.php` — GLONASSSoft **UMKa 310 v2L**, default `traccar`, fallback `sms`, `sms_open_command 'OUTPUT0=1'`.
- `app/Http/Admin/V1/Requests/Devices/RegisterDeviceRequest.php` + `UpdateDeviceRequest.php` — `driver_type` `in:traccar,ble,sms`.

### Wiring
- `app/Providers/IntegrationsServiceProvider.php` — bind `TraccarClient` + `DeviceSmsGateway` (fake in testing / `*_DRIVER=fake`, else HTTP).
- `app/Domain/DeviceComm/ModuleServiceProvider.php` — bind `device-driver.traccar` / `device-driver.sms` (ble slot reserved).
- `app/Domain/DeviceComm/Services/DriverResolver.php` — simplified to per-device `driver_type` + container lookup (operator-fallback policy retired with CLIP).
- `app/Domain/DeviceComm/Services/OpenCommandService.php` — added `confirmActuation()` (telemetry upgrades `dispatched` → `opened`).
- `app/Domain/DeviceComm/Contracts/DeviceDriver.php` — docblock updated to v1.2 taxonomy.
- `app/Http/Admin/V1/Controllers/Devices/AdminDeviceCommController.php` — added `diagnostics()`.
- `routes/admin.php` — `adminDeviceDiagnostics`. `routes/webhooks.php` — `traccarForward`. `routes/console.php` — schedule `WhitelistSyncJob` every minute.
- `config/domain/device_comm.php` — drivers `[traccar,sms]`; transient/non-transient codes; completion-ms defaults; `open_command`.

### Tests updated for the enum change (09-A kept green)
- `tests/Pest.php` — helper `default_driver_type` `clip_sms`→`traccar`; added `openableWithRoster()`.
- `tests/Feature/DeviceComm/CommandDispatchTest.php` — reworked confirming/non-confirming/fallback around traccar/sms; `ble` exercises the unsupported path.
- `tests/Feature/DeviceComm/OpenDeviceTest.php` — `driver_confirms_actuation` true (traccar).
- `tests/Feature/Devices/TechRegisterDeviceTest.php` + `AdminDeviceCrudTest.php` — payload driver `traccar`.

### Deleted (transport pivot)
- `config/integrations/voice.php` — voice gateway not part of v1.2 transport.
- `app/Domain/DeviceComm/Support/OperatorFallbackPolicy.php` — CLIP caller-ID fallback retired (CRIT-01).

### Docs reconciled
- `docs/PROJECT_CONSTITUTION.md` — R-DOM-05 (BLE exception withdrawn) + R-GSM-01 (traccar/sms primary, ble reserved).

---

## 3. Migration summary

`php artisan migrate:fresh --seed` → clean (all 31 migrations DONE; seeders DONE).

| Migration | Table | Notes |
|---|---|---|
| `..._070005_create_device_diagnostics_table` | `device_diagnostics` | Partitioned raw SQL, PK `(id, partition_key)`, monthly RANGE, no FK (app-enforced — DB Arch §6.2). Columns: source enum, online, signal_strength, battery_level, firmware_version, raw JSON, reported_at. |
| `..._070006_create_traccar_devices_table` | `traccar_devices` | `device_id` (FK → devices, unique), `traccar_id` (nullable), `unique_id` (unique), `registered_at`. |

No change to the partitioned `open_commands` table. Enum edits to existing `devices`/`device_models` migrations apply on a fresh migrate (as designed for the pre-production stage).

---

## 4. Route summary

| Method | Path | Name | Auth |
|---|---|---|---|
| POST | `/v1/devices/{deviceId}/open` | openDevice | user JWT (09-A) |
| GET | `/v1/devices/{deviceId}/commands` | listDeviceCommands | user JWT (09-A) |
| GET | `/v1/commands/{commandId}` | getCommand | user JWT (09-A) |
| POST | `/v1/commands/{commandId}/feedback` | submitOpenFeedback | user JWT (09-A) |
| GET | `/admin/v1/devices/{deviceId}/commands` | adminDeviceCommands | admin JWT (09-A) |
| GET | `/admin/v1/devices/{deviceId}/whitelist-queue` | adminWhitelistQueue | admin JWT (09-A) |
| POST | `/admin/v1/devices/{deviceId}/whitelist/resync` | adminResyncWhitelist | admin (technical/super) (09-A) |
| **GET** | **`/admin/v1/devices/{deviceId}/diagnostics`** | **adminDeviceDiagnostics** | **admin JWT (NEW 09-B)** |
| **POST** | **`/v1/traccar/forward`** | **traccarForward** | **shared-secret token (NEW 09-B)** |

Scheduled: `WhitelistSyncJob` every minute (`->withoutOverlapping()->onOneServer()`), alongside 09-A's `ExpireStaleOpenCommandsJob`.

---

## 5. Test results

```
php artisan test
Tests:    225 passed (743 assertions)
Duration: ~26s
```

- 09-A baseline (202) + 23 new 09-B tests, all green; no skips, no incomplete.
- DeviceComm suite: 55 passed (179 assertions).
- Real-driver fallback proven end-to-end: TraccarDriver (offline → `device_offline` transient) → SmsDriver dispatch (`OUTPUT0=1` to SIM) via `CommandDispatcher`.
- Actuation read-back proven: event-forward with output-on upgrades a recent `dispatched` open to `opened`.

---

## 6. OpenAPI validation results

```
php docs/openapi/validate.php
Defined: schemas=81, parameters=7, responses=7, headers=4, securitySchemes=4
Unique refs used: 96
All $refs resolve.
operationIds: 100 (all unique)
tags: 26 declared, 26 used (all valid)
```

- `adminDeviceDiagnostics` operation, `DeviceDiagnostic` + `DeviceDiagnosticListResponse` schemas already present from the v1.2 doc pass; the `DeviceDiagnosticResource` payload matches the schema field-for-field (source enum, signal_strength 0–31, nullable battery/firmware).
- Driver enum `[traccar, ble, sms]` consistent across the 5 documented sites.
- `FakeTraccarClient` / `FakeDeviceSmsGateway` are test doubles (like `FakeKapitalGateway`), not part of the public contract.

---

## 7. Known limitations (carried forward / Phase-0 confirmable)

1. **Traccar command framing unproven (HB1/T1).** `HttpTraccarClient::sendCommand()` sends `type: custom`, `attributes.data = "OUTPUT0=1"`. The exact framing the UMKa 310 v2L accepts over its live Wialon session is confirmable only against a real device + Traccar (Phase-0 T1). The contract/seam is correct; only the wire detail is pending.
2. **Output-bit attribute key (T3).** Actuation confirmation reads `config('integrations.traccar.output_attribute')` (default `output`) from the position attributes. The real attribute key/casing the UMKa emits is Phase-0 confirmable; configurable without code change.
3. **`cmdout`-style trigger / 200-vs-202 semantics.** Online→`sent`, offline→`queued`→`device_offline` (transient) mapping assumes Traccar's documented status codes; confirm against the deployed Traccar build.
4. **Whitelist is a no-op under Traccar.** Authorisation is platform-side (server-authorised model), so `whitelistAdd/Remove` complete the outbox immediately as `synced`. The outbox is retained as the audit channel and the future BLE/credential-provisioning path.
5. **`open_command_attempts.voice_gateway_id`** remains as a nullable, now-unused 09-A column (left untouched to keep the 09-A control plane unchanged); always written `null` under v1.2.
6. **BLE deferred.** No `BleProvisioningDriver`, no `device-driver.ble` binding, no BLE entitlements, no R-DOM-05 BLE exception, no CLIP/Voice Gateway/MQTT — per scope.
7. **SMS provider field mapping.** `HttpDeviceSmsGateway` posts `{sender, to, message}`; confirm against the chosen AZ SMS vendor's API at integration time.

---

## 8. Stop point

Batch 09-B is complete and verified. Per instruction, stopping here for review before any further work
(e.g. a future BLE batch or live Phase-0 hardware validation).
