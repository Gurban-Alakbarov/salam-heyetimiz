# Batch 08 — Devices — Review Package

**Version:** 1.0
**Date:** 2026-06-14
**Scope:** the Devices bounded context only (device registration/provisioning, ownership + roster
assignment, device detail, device-wide status transitions, transfer, admin management, per-caller
device views, and the device-sale assignment trigger). **No DeviceComm/GSM code** (next batch) was
written — whitelist programming, open commands, diagnostics and open-derived stats are deferred and
driven via the events emitted here.
**Sources of truth:** PROJECT_CONSTITUTION §R-DOM-05/06/07/10/11/12/13/14 / R-API-03,
TECHNICAL_SPECIFICATION §3, DATABASE_ARCHITECTURE §3.1–3.3, BACKEND_ARCHITECTURE §14.4, openapi/v1.yaml.

## Verification (run on Windows / PHP 8.2.12 / MariaDB 10.4.32)

| Step | Result |
|---|---|
| `php artisan test` (full suite) | ✅ **159 passed, 553 assertions**, 0 failed (131 prior + **28 Devices**) |
| `php artisan route:list` | ✅ 67 routes total; **12 new device routes** |
| `php docs/openapi/validate.php` | ✅ green (100 operationIds, 96 refs resolve, 26 tags) |
| `php artisan migrate` | n/a — **no new migrations** (the devices / device_users / device_user_history tables shipped in batch 04) |

---

## 1. File tree (batch 08)

```
app/
├── Domain/Devices/
│   ├── ModuleServiceProvider.php                 # DevicePolicy + OrderPaid (DeviceAssigner) listener
│   ├── Enums/                                     # (reused) DeviceStatus, DriverType, SimStatus
│   │   └── DeviceListFilter.php                    # NEW — listMyDevices filter
│   ├── Models/Device.php                          # (reused) relations: model/simOperator/region/owner/deviceUsers
│   ├── DTOs/                                       # DeviceRegistrationData, DeviceAssignmentData, DeviceTransferData
│   ├── Exceptions/DeviceAlreadyAssignedException.php  # 409
│   ├── Events/                                     # 7: DeviceRegistered, DeviceAssigned, DeviceTransferred,
│   │                                               #   DeviceDisabled, DeviceEnabled, DeviceDecommissioned, DeviceUpdated
│   ├── Services/                                   # 3: DeviceProvisioningService (register/update),
│   │                                               #   DeviceOwnershipService (assign/transfer + roster history),
│   │                                               #   DeviceStatusService (disable/enable/decommission)
│   ├── Queries/DeviceQuery.php                     # forUser(filter) + adminList(status/owner/region/q)
│   ├── Policies/DevicePolicy.php                   # owner / active member / admin
│   ├── Actions/                                    # 7: Register, AssignOwner, Update, Disable, Enable,
│   │                                               #   Decommission, Transfer
│   ├── Listeners/AssignPurchasedDeviceOnOrderPaid.php   # R-DOM-13 DeviceAssigner
│   └── Jobs/RevokeDeviceRosterJob.php              # async roster revoke on decommission (R-DOM-11)
├── Http/
│   ├── Concerns/PresentsAdminDevice.php            # relation load + whitelist_used (R-DOM-14)
│   ├── Api/V1/Controllers/Devices/                 # DeviceController (mobile), TechDeviceController
│   ├── Admin/V1/Controllers/Devices/AdminDeviceController.php
│   ├── Admin/V1/Requests/Devices/                  # Register, Assign, Update, Disable, Decommission, Transfer
│   └── Resources/                                  # DeviceResource, DeviceDetailResource, DeviceAdminResource,
│                                                   #   DeviceAdminDetailResource, DeviceUserResource,
│                                                   #   SubscriptionBriefResource, UserBriefResource,
│                                                   #   SimOperatorResource, DeviceModelResource, RegionResource
tests/Feature/Devices/   ListMyDevicesTest · GetDeviceTest · TechRegisterDeviceTest · TechAssignDeviceTest ·
                         AdminDeviceCrudTest · AdminDeviceStatusTest · AdminTransferDeviceTest ·
                         DeviceAssignerOnOrderPaidTest
```

**Touched (wiring):** `routes/api.php` (user `devices` group + `technical/devices` admin-guarded group),
`routes/admin.php` (`admin/devices` CRUD + status/transfer), `app/Domain/Subscriptions/Queries/SubscriptionQuery.php`
(`forDeviceUser` / `forUserDevice` reads for device subscription briefs — R-ARCH-06),
`lang/{az,ru,en}/errors.php` (+`device_already_assigned`), `tests/Pest.php` (`makeDeviceModel`,
`makeOwnedDevice` helpers).

---

## 2. Implemented endpoints (all per openapi/v1.yaml)

| operationId | Method | Path | Guard / role | Notes |
|---|---|---|---|---|
| listMyDevices | GET | `/v1/devices` | auth:user | owned + member; per-caller role/can_open/suspension; filter all/owned/member/suspended |
| getDevice | GET | `/v1/devices/{id}` | auth:user | DeviceDetail (model + subscription brief); 404 for non-members |
| techRegisterDevice | POST | `/v1/technical/devices` | auth:admin, technical/super | 201 DeviceAdmin in `unassigned` |
| techAssignDevice | POST | `/v1/technical/devices/{id}/assign` | auth:admin, technical/super | binds owner by phone, activates; 409 if already assigned |
| adminListDevices | GET | `/admin/v1/devices` | auth:admin | filters status/owner_user_id/region_id/q |
| adminCreateDevice | POST | `/admin/v1/devices` | auth:admin, technical/super | 201 DeviceAdmin |
| adminGetDevice | GET | `/admin/v1/devices/{id}` | auth:admin | DeviceAdminDetail (+ roster users) |
| adminUpdateDevice | PATCH | `/admin/v1/devices/{id}` | auth:admin, technical/super | partial update |
| adminDecommissionDevice | DELETE | `/admin/v1/devices/{id}` | auth:admin, super | 204; soft-delete + async roster revoke |
| adminDisableDevice | POST | `/admin/v1/devices/{id}/disable` | auth:admin, super | block opens, keep history |
| adminEnableDevice | POST | `/admin/v1/devices/{id}/enable` | auth:admin, super | restore to assigned state |
| adminTransferDevice | POST | `/admin/v1/devices/{id}/transfer` | auth:admin, super | re-owner (R-DOM-12); keep_existing_users |

> **Technical mobile mode:** the `/v1/technical/*` routes live on the mobile host but require an
> **admin** JWT (`adminBearerAuth`) and the technical/super role — a field technician using the mobile
> app. A mobile user token is rejected (401).

### Behaviours implemented
Register a physical unit into `unassigned` (server-validated unique serial/sim_phone, FK model/region/
operator) · bind an owner (resolve-or-provision by phone — R-DOM-01) which activates the device and
seeds the owner's roster row, append-logged to `device_user_history` (R-DOM-11) · **device-sale
assignment trigger** — `OrderPaid` with a `device` item binds the payer as owner (R-DOM-13), idempotent ·
device-wide status transitions disable/enable/decommission (R-DOM-06) with decommission soft-deleting
and asynchronously revoking the roster · admin-mediated transfer (R-DOM-12) revoking the prior owner and
optionally retaining members · per-caller `role`/`can_open`/`suspension_reason` derived via the §13.9
read (R-DOM-07) · `whitelist_capacity_used` computed on read from the active roster (R-DOM-14) · 7 device
audit events (AuditableEvent) for the batch-09 audit + DeviceComm reactors.

---

## 3. Database changes

**None.** The Devices context runs entirely over the existing schema (DB Arch §3.1–3.3): `devices`
(status enum, owner FK, sim/location/health columns, soft-deletes, `whitelist_capacity_used` deprecated
per R-DOM-14), `device_users` (STORED `is_active` active-uniqueness, R-DOM-10), and the append-only
`device_user_history` (R-DOM-11). This batch is pure application logic + HTTP surface over that schema.

---

## 4. Test results

```
PASS  Feature/Devices/ListMyDevicesTest          (5)   owned/member, filters, can_open, auth
PASS  Feature/Devices/GetDeviceTest              (3)   detail + model + subscription; 404 non-member/missing
PASS  Feature/Devices/TechRegisterDeviceTest     (4)   201 unassigned; dup serial / unknown model 422; user token 401
PASS  Feature/Devices/TechAssignDeviceTest       (2)   bind owner + activate + roster + history; 409 already assigned
PASS  Feature/Devices/AdminDeviceCrudTest        (5)   create, list+filter+search, detail+users, partial update, auth
PASS  Feature/Devices/AdminDeviceStatusTest      (4)   disable, enable, decommission (soft-delete + roster revoke), 403 non-super
PASS  Feature/Devices/AdminTransferDeviceTest    (3)   transfer keep-members, revoke-members, 403 non-super
PASS  Feature/Devices/DeviceAssignerOnOrderPaidTest (2) R-DOM-13 bind payer; idempotent replay

Devices: 28 passed   ·   Full suite: 159 passed (553 assertions)   ·   ~22s (MariaDB, R-CODE-09)
```

Notable cases proving the spec-critical paths:
- **R-DOM-13 DeviceAssigner:** a paid order with a `device` item binds the payer as owner, activates the
  device, and seeds the owner roster row; replaying `OrderPaid` does not double-assign (idempotent).
- **R-DOM-12 transfer:** the prior owner's roster row is revoked, the new owner becomes active owner, and
  existing members are kept or revoked per `keep_existing_users` — all append-logged.
- **R-DOM-11 history:** every assign/transfer/decommission roster mutation writes a `device_user_history`
  row (added / revoked), never a silent overwrite.
- **R-DOM-07 per-caller view:** an owner with an active subscription sees `can_open=true` / `suspension_reason=none`;
  the §13.9 read (batch 06) drives this. Non-members get 404 on `getDevice` (no existence leak).
- **Role gates:** decommission/disable/enable/transfer are super_admin-only (403 for technical); register/
  update/assign allow technical or super.

---

## 5. Coverage summary

- **Functional coverage:** every "Implement:" bullet (registration, ownership, details, status,
  provisioning, transfer preparation, suspension states, search/listing, owner permissions, admin
  management, device events) has at least one passing test. The cross-module trigger (R-DOM-13) and the
  roster/history invariants (R-DOM-11/12) are covered directly.
- **Line coverage:** not measured — no pcov/xdebug in this XAMPP build. Enable pcov and run
  `php artisan test --coverage --min=70` (R-CODE-09).

---

## 6. Design notes & boundaries (for review)

1. **"Transfer preparation" = data + event.** `adminTransferDevice` / `techAssignDevice` perform the
   ownership + roster + history changes and emit `DeviceAssigned` / `DeviceTransferred`. The physical
   **whitelist reprogramming** (pushing the owner's phone to the device) is DeviceComm's reaction to
   those events in batch 09 — intentionally not done here ("Do not start DeviceComm/GSM").
2. **DeviceComm-owned fields are deferred, not mocked.** `getDeviceStats` (DeviceStats) and
   `DeviceAdminDetail.stats` are open-command aggregations — `open_commands` does not exist yet, so
   `getDeviceStats` is **not implemented** (would require mock data or the DeviceComm table) and the
   admin-detail `stats` block is **omitted**. `cooldown_seconds_remaining` is `null` (open-cooldown is
   DeviceComm's Redis state). Also deferred to DeviceComm/GSM: `adminResyncWhitelist`,
   `adminDeviceCommands`, `adminDeviceDiagnostics`, `adminWhitelistQueue`, `techDiagnosticsPing`,
   `openDevice`, `listDeviceCommands`.
3. **Per-caller device fields** (`role`, `can_open`, `suspension_reason`) are computed per request from
   the §13.9 `SubscriptionStatusQuery` (batch 06) and attached to the model before serialization. `role`
   is derived from `owner_user_id` (authoritative) vs the roster row.
4. **`whitelist_capacity_used` is computed on read** from the active roster count (R-DOM-14 — the column
   is deprecated and never written).
5. **Decommission revokes the roster via a queued job** (`RevokeDeviceRosterJob`) because a device may
   carry many members; the device row is soft-deleted (terminal). In tests (sync queue) this runs inline.
6. **Technical mobile mode** routes are on the mobile host (`/v1/technical/*`) but authenticated by the
   **admin** guard + role check — matching the OpenAPI `adminBearerAuth` + `x-authorization: technical`.
7. **Owner provisioning on assignment/transfer** uses `firstOrCreate` by phone (phone is canonical,
   R-DOM-01); an owner who is not yet a registered user is created in `active` state.
8. **Roster sub-user management (invite/add/remove), `listDeviceRoster`, invitations** are a separate
   Roster concern (their models exist from batch 04) and are **not** in this batch — Devices owns the
   device record, ownership and device-wide status only.

---

*End of Batch 08 Review v1.0. Stopping here — Devices complete, **not** starting DeviceComm/GSM. Awaiting review.*
