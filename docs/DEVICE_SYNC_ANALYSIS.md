# Device Sync Analysis — why the UMKa is in Traccar but not the Admin Panel

**Date:** 2026-06-27
**Scope:** investigation only — **no code changed, nothing fixed.** Traces the full path UMKa → Traccar →
Webhook → Laravel → DB → Admin API → React UI and shows the exact break point.
**Device:** UMKa 310 v2L, IMEI/uniqueId `868184062169571` (online in Traccar as device `id=1 "Sinam Test"`).

---

## TL;DR

The Admin Panel reads the **Laravel `devices` table**, which is **empty** (`devices = 0`). The UMKa exists
**only inside Traccar's own database** (created by hand in the Traccar web UI as "Sinam Test"). The webhook that
carries its telemetry into Laravel **looks up the device by `traccar_devices.unique_id`, finds no row, and
discards the payload as "unknown device."** Nothing in the system creates a Laravel `devices` row (or the
`traccar_devices` mapping) from Traccar — so the device never reaches the Admin Panel.

**The chain stops at** `TraccarIngestionService::ingest()` line ~35: `if ($mapping === null) return false;`.

---

## The complete path — and where it breaks

```
UMKa 310  (uniqueId 868184062169571)
   │  Wialon IPS → gps.salamheyetimiz.com:5011
   ▼
TRACCAR        ✅ device id=1 "Sinam Test", ONLINE, fresh telemetry (output:"0", sat 48)
   │           ⚠️ registered DIRECTLY in the Traccar web UI — bypassing Laravel
   │  forward.enable=true → POST http://host.docker.internal/v1/traccar/forward?token=…  (json)
   ▼
nginx → Laravel  ✅ 5× POST /v1/traccar/forward → HTTP 200
   ▼
TraccarForwardController  ✅ token matches (hash_equals) → calls ingestion
   ▼
TraccarIngestionService::ingest($payload)
   │  $uniqueId = "868184062169571"
   │  $mapping  = TraccarDeviceMapper::byUniqueId("868184062169571")
   │            = SELECT * FROM traccar_devices WHERE unique_id = '868184062169571'
   │            = NULL          ← table is EMPTY (traccar_devices = 0)
   ▼
   if ($mapping === null) return false;   ❌❌❌  CHAIN STOPS HERE — "unknown device, ignore"
   │
   ✗ no device_diagnostics row written   (device_diagnostics = 0)
   ✗ no devices row touched
   ✗ no devices/traccar_devices row created  (the webhook NEVER creates devices — by design)
   ▼
devices table            = 0 rows
   ▼
GET /admin/v1/devices → AdminDeviceController::index → DeviceQuery::adminList() → SELECT … FROM devices → []
   ▼
React UI  useDevices() → /admin/v1/devices → []  →  UMKa NOT shown
```

**Production ground truth (verified 2026-06-27):** `devices = 0`, `traccar_devices = 0`,
`device_diagnostics = 0`; nginx shows 5 forward POSTs all `200`; Traccar telemetry is live and correct.
Two disjoint registries: the device lives in **Traccar's** `tc_devices`, not in **Laravel's** `devices`.

---

## Answers to the five questions

### 1. Does the Admin UI read devices from Laravel or directly from Traccar?
**From Laravel — never from Traccar.** `admin-ui/src/api/devices.ts` → `useDevices()` calls
`GET /admin/v1/devices`. The React app has no Traccar client at all. The Traccar web UI ("Sinam Test") and the
Admin Panel are **two separate systems with two separate databases**; they are only ever linked by the
`traccar_devices` mapping row (which doesn't exist here).

### 2. Which database table powers `GET /admin/v1/devices`?
The **`devices`** table (Laravel app DB). Path: `AdminDeviceController::index` →
`DeviceQuery::adminList(...)` → Eloquent on `App\Domain\Devices\Models\Device` → `devices`. It does **not**
read `traccar_devices`, and it does **not** read Traccar's `tc_devices`.
Note: `devices` has **no `imei` column** — device identity there is `serial` + `sim_phone`. The IMEI/uniqueId
only ever lives in `traccar_devices.unique_id`, after a device is mapped.

### 3. Why is the UMKa not appearing there?
Because **there is no `devices` row for it.** It was created **only in Traccar** (manually, via the Traccar
web UI). Nothing has created the corresponding Laravel `devices` row, and nothing has created the
`traccar_devices` mapping that would let the webhook recognise it. The Admin Panel faithfully shows the
`devices` table, which is empty — so it correctly shows zero devices.

### 4. Was Batch 09-B supposed to automatically create Laravel devices from Traccar webhooks?
**No — that is intentional design, not a missing piece.** `TraccarIngestionService` is documented and coded to
**ignore unknown devices** ("Unknown devices are ignored"; `if ($mapping === null) return false;`). The
event-forward webhook is a **telemetry sink for already-provisioned devices**, not a provisioning source.
Auto-materialising app devices from inbound telemetry would be a data-integrity / security hole — any device
that ever connected to Traccar (or any spoofed `uniqueId`) would silently create app rows. So the webhook
**not** creating devices is correct and deliberate.

### 5. Is the webhook currently creating anything?
**For this UMKa: nothing.** Unknown → ignored → returns immediately; `device_diagnostics = 0`.
For a **mapped** device it would create `device_diagnostics` rows, update `devices.last_online_at` /
`last_signal_strength`, and confirm a recent `dispatched` open (output-bit read-back). But it would **never**
create `devices` or `traccar_devices` rows. Today it is a complete no-op for the UMKa.

---

## What checked out, file by file

| Component | What it does | State for the UMKa |
|---|---|---|
| `TraccarForwardController` | token gate → `ingestion->ingest($request->all())`; always returns 200 | ✅ working (200) |
| `TraccarIngestionService::ingest()` | `byUniqueId()` → if null **return false (ignore)**; else diagnostics + status + open-confirm | ❌ returns false (no mapping) |
| `TraccarDeviceMapper::byUniqueId()` | `SELECT * FROM traccar_devices WHERE unique_id = ?` | returns NULL (table empty) |
| `TraccarDeviceMapper::register()` | registers device in Traccar **and writes the `traccar_devices` mapping** | **never called in any prod path** (see below) |
| `devices` table | Admin source of truth; identity = serial + sim_phone (no imei) | 0 rows |
| `traccar_devices` table | link row: `device_id` (FK, unique), `traccar_id`, `unique_id` (unique), `registered_at` | 0 rows |
| `DeviceProvisioningService::register()` | creates a `devices` row + dispatches `DeviceRegistered` | not invoked (no admin device created) |
| `AdminDeviceController::index` / `DeviceQuery::adminList` | reads `devices` | returns [] |
| `admin-ui` `useDevices()` | `GET /admin/v1/devices` | renders [] |

---

## The missing link: bug, unfinished feature, or intentional design?

There are **two distinct provisioning directions**; the confusion comes from conflating them.

**(a) Traccar → Laravel auto-create (webhook materialises an app device):**
**Intentionally absent — correct design.** The webhook ignores unknown devices on purpose (§4). This is not a
bug and should not be "fixed" by making the webhook create devices.

**(b) Laravel → Traccar registration (provisioning pushes a new app device into Traccar + writes the mapping):**
**Unfinished feature.** The intended flow is:
```
admin POST /admin/v1/devices → DeviceProvisioningService::register() → devices row + DeviceRegistered event
                                                  … then …
TraccarDeviceMapper::register($device, $imei) → Traccar registerDevice() + INSERT traccar_devices(unique_id=IMEI)
```
The mapper method **exists and is unit-tested** (`TraccarDeviceMapperTest`), but **nothing calls it in
production**. Verified: across `app/**`, the only callers of a `register(...)` are
`DomainServiceProvider` (`$this->app->register()`, unrelated) and `RegisterDevice` (`provisioning->register()`,
which only writes the `devices` row). There is **no listener on `DeviceRegistered`** that registers the device
in Traccar — the only device-event listeners are whitelist-related (Assigned / Transferred / Decommissioned).
So even if an admin created the UMKa in the Admin Panel today, it would still get **no `traccar_devices` row**,
opens would fail with `device_not_registered`, and its telemetry would still be ignored by the webhook.

**(c) This specific device — a third, situational issue:** the UMKa was created **only in the Traccar UI**,
never in Laravel. Even a fully-wired flow (b) would not retroactively adopt it; a device must exist in Laravel
`devices` first, then be mapped (or be reconciled by `uniqueId`).

### Classification

| Layer | Verdict |
|---|---|
| Webhook auto-creating app devices from telemetry | **Intentional design (correct).** Not a bug. |
| Laravel→Traccar registration wired into provisioning | **Unfinished feature.** Mapper built + tested, never wired to a listener/flow. |
| The UMKa existing only in Traccar UI | **Operational / data state.** Hand-created in Traccar, no Laravel counterpart. |

**Net:** the Admin Panel is behaving correctly given an empty `devices` table. The reason it's empty is an
**unfinished provisioning link (b)** — the device was never registered through Laravel — compounded by the
device having been **created directly in Traccar (c)**. It is **not** a webhook bug, and the webhook is
**not** supposed to create it.

---

## Where to look if/when this is addressed later (no action taken here)

- Provisioning wiring: a `DeviceRegistered` (or device-assigned) listener that calls
  `TraccarDeviceMapper::register($device, $imei)` — and a way to capture the **IMEI/uniqueId** at device
  creation (today `devices` has no `imei` column; `RegisterDeviceRequest` would need an identifier that flows
  into `traccar_devices.unique_id`).
- Reconciliation for already-in-Traccar devices: a one-off/admin path to create the Laravel `devices` row +
  `traccar_devices` mapping for an existing Traccar `uniqueId` (the UMKa's case).

*(Listed for orientation only — explicitly out of scope; nothing was changed.)*
