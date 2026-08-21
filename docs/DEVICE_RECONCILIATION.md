# Device Reconciliation — adopting an existing Traccar device into Laravel

**Date:** 2026-06-27
**Status:** implemented, tested (232 passing), deployed to production, and exercised end-to-end on the real
UMKa (IMEI `868184062169571`). The device now appears in the Admin Panel and its telemetry is ingested.

This document is the official architecture for **devices that were created directly in Traccar** (e.g. in
the Traccar web UI) and therefore have no Laravel record. It explains the flow that **adopts** such a device
into the platform without ever creating a second Traccar device.

---

## Background — the two provisioning directions

There are two, deliberately separate, directions. Conflating them is what caused the confusion
(see `DEVICE_SYNC_ANALYSIS.md`).

| Direction | Method | When |
|---|---|---|
| **Laravel → Traccar** (provision a NEW Traccar device) | `TraccarDeviceMapper::register()` | a device is born in Laravel and pushed into Traccar |
| **Traccar → Laravel** (adopt an EXISTING Traccar device) | **`TraccarReconciliationService` (new)** | a device already exists in Traccar and must be back-filled into Laravel |

Reconciliation is the second direction. It **never** registers a device in Traccar — it looks the existing
one up, then writes the Laravel rows that link to it.

**Unchanged by design (security model intact):**
- The Traccar **event-forward webhook still never auto-creates devices** from telemetry (unknown `uniqueId`
  → ignored). Auto-materialising devices from inbound packets would let any device that connects to Traccar
  (or any spoofed `uniqueId`) create app rows. Reconciliation is an **explicit, authenticated, admin/ops
  action** instead.
- The webhook design, token gate, and ingestion logic are untouched. Once the mapping exists, the webhook
  simply stops ignoring the device because `TraccarDeviceMapper::byUniqueId()` now resolves it.

---

## The reconciliation flow

```
adopt(uniqueId = 868184062169571, + device fields: serial, model, sim_phone, …)
   │
   ├─ 1. Already mapped?  traccar_devices.unique_id == uniqueId ?
   │        └─ yes → return existing device  (idempotent no-op, created=false)
   │
   ├─ 2. TraccarClient::findDeviceByUniqueId(uniqueId)   →  GET /api/devices?uniqueId=…
   │        └─ null → throw TraccarDeviceNotFoundException   (adopt-only: we NEVER create a Traccar device)
   │        └─ found → remote = { id: 1, name: "Sinam Test", … }
   │
   └─ 3. DB transaction:
            a. DeviceProvisioningService::register(data)   →  INSERT devices (status=unassigned) + DeviceRegistered event
            b. TraccarDeviceMapper::adopt(device, remote.id, uniqueId)
                                                            →  INSERT traccar_devices(device_id, traccar_id=remote.id, unique_id)
            → DeviceReconciliationResult{ device, mapping, created=true }
```

**The link it establishes:** `Laravel devices.id  ↔  traccar_devices.traccar_id (= Traccar device id)  ↔  traccar_devices.unique_id (= IMEI)`.

`TraccarDeviceMapper::adopt()` is the key new primitive: it writes the mapping to a **given, pre-existing**
Traccar id and **does not** call `TraccarClient::registerDevice()` — so the Traccar device is never
duplicated. (`register()` remains the Laravel→Traccar path that *does* create a Traccar device.)

---

## Surfaces (two ways to run it — same service)

### 1. Artisan command (the ops tool for direct-in-Traccar devices)
```
php artisan devices:reconcile-traccar <uniqueId>
    --serial=<serial> --model=<device_model_id> --sim-phone=+994#########
    [--driver=traccar] [--sim-operator=<id>] [--firmware=<v>] [--region=<id>] [--label="..."]
```
Idempotent: re-running for an already-mapped `uniqueId` is a no-op (`Already mapped: …`). It bypasses HTTP
form validation (ops context) but goes through the same service + DB constraints.

### 2. Admin API endpoint (the official programmatic surface)
```
POST /admin/v1/devices/reconcile        (auth:admin, role: technical or super_admin)
Body: { serial, device_model_id, sim_phone, driver_type, unique_id, … }
→ 201 (newly reconciled)  |  200 (already mapped)  |  422 (unique_id not present in Traccar / validation)
```
Returns the `DeviceAdmin` resource, identical to `POST /admin/v1/devices`. Same authorisation as device
creation (`ensureTechnical`). `TraccarDeviceNotFoundException` → `422 { error: { fields: { unique_id } } }`.

Both call **`TraccarReconciliationService::reconcile()`** — one implementation, one behaviour.

---

## Files

**New**
- `app/Domain/DeviceComm/Services/TraccarReconciliationService.php` — orchestrates the flow (idempotent, transactional).
- `app/Domain/DeviceComm/DTOs/TraccarRemoteDevice.php` — a device read back from Traccar (id, uniqueId, name, status).
- `app/Domain/DeviceComm/DTOs/DeviceReconciliationResult.php` — `{ device, mapping, created }`.
- `app/Domain/DeviceComm/Exceptions/TraccarDeviceNotFoundException.php` — adopt-only hard stop.
- `app/Console/Commands/ReconcileTraccarDeviceCommand.php` — `devices:reconcile-traccar`.
- `app/Http/Admin/V1/Requests/Devices/ReconcileDeviceRequest.php` — registration fields + `unique_id`.

**Changed**
- `app/Domain/DeviceComm/Contracts/TraccarClient.php` — `+ findDeviceByUniqueId()`.
- `app/Domain/DeviceComm/Adapters/HttpTraccarClient.php` — `GET /api/devices?uniqueId=` impl.
- `app/Domain/DeviceComm/Adapters/FakeTraccarClient.php` — in-memory impl + `seedRemoteDevice()` for tests.
- `app/Domain/DeviceComm/Services/TraccarDeviceMapper.php` — `+ adopt()` (map to existing Traccar id, no create).
- `app/Http/Admin/V1/Controllers/Devices/AdminDeviceController.php` — `+ reconcile()`.
- `routes/admin.php` — `+ POST devices/reconcile` (adminReconcileDevice).

**Tests** — `tests/Feature/DeviceComm/TraccarReconciliationTest.php` (6) + 1 added to `TraccarDeviceMapperTest`:
adopts-not-duplicates, refuses-when-not-in-Traccar, idempotency, webhook-stops-ignoring, endpoint adopts +
appears in admin list, endpoint 422 when absent. Suite: **232 passing**.

---

## Production verification (real UMKa, 2026-06-27)

Ran `php artisan devices:reconcile-traccar 868184062169571 --serial=22061138 --model=1 --sim-phone=+994505384489 --sim-operator=1 --firmware=2.6.6 --label="Sinam Test"`.

| Requirement | Result |
|---|---|
| Traccar device **not** duplicated | ✅ still a single Traccar device `id=1 "Sinam Test"` |
| Laravel `devices` row created | ✅ `id=1, serial=22061138, model=UMKa 310 v2L, status=unassigned` |
| `traccar_devices` mapping created | ✅ `device_id=1 ↔ traccar_id=1 ↔ unique_id=868184062169571` |
| Idempotent re-run | ✅ `Already mapped`; `devices` stayed at 1 (no duplicate, no second Traccar device) |
| Webhook no longer ignores telemetry | ✅ real forwards now ingested (were dropped before) |
| `device_diagnostics` receives records | ✅ rows accumulating every ~5 min (`reported_at` 13:22:56, 13:27:56, …); device `last_online_at` + `last_signal_strength=48` updated |
| `GET /admin/v1/devices` returns it | ✅ `DeviceQuery::adminList` → 1 row; `DeviceAdminResource` serializes id/serial/status/model |
| Admin UI shows it | ✅ the UI calls that endpoint, which now returns the device |

Deployment: 12 files uploaded to `/var/www/salam`, `composer dump-autoload -o` (new classes), `route:cache`
rebuilt, `php8.4-fpm` reloaded, `salam-horizon` restarted. No migration (no schema change). No backend
business logic or webhook design changed.

---

## Notes / boundaries

- **Status after reconciliation is `unassigned`.** The device is now visible and manageable in the Admin
  Panel; assigning an owner is the normal next admin step (separate flow).
- **No IMEI column on `devices`.** Device identity in `devices` is `serial` + `sim_phone`; the IMEI/uniqueId
  lives in `traccar_devices.unique_id` (the cross-system key). Reconciliation supplies both.
- **The webhook/security model is unchanged.** Reconciliation is the *only* way a Traccar-origin device
  enters Laravel, and it is explicit + authenticated. Telemetry never auto-creates devices.
- **Forward-direction wiring (Laravel→Traccar on device registration) remains intentionally not auto-wired**
  (`DEVICE_SYNC_ANALYSIS.md` §b). This change adds the *reconciliation* (Traccar→Laravel) path only, as
  requested; it does not alter how brand-new Laravel devices are pushed to Traccar.
