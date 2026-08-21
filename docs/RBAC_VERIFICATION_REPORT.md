# RBAC Verification Report

**Date:** 2026-06-27 · Method: every role re-verified against the matrix by minting a real admin JWT per
role and exercising the live admin API on production (`admin.salamheyetimiz.com`), plus the full automated
suite (261 passing). No assumptions — each endpoint's status code was checked against the expected value.

---

## What was wrong

| # | Problem | Root cause |
|---|---|---|
| 1 | **Operator (and finance/support/complex_manager) could not see "Sahib təyin et", "Sakin əlavə et", "Çıxar"** | The UI gated those buttons with `RoleGate roles={['technical','super_admin']}` — a leftover from before RBAC. The **backend** correctly allowed operator (devices.assign / residents.create / residents.delete), but the **frontend** still hid the controls by role list, not permission. |
| 2 | **Technical still saw "Assign"** | `devices.assign` was granted to `technical` in the matrix (earlier backward-compat), contradicting the spec ("Technical must NOT see Assign"). |
| 3 | **Device summary "Offline" but Diagnostics rows "Online"** | The summary used current connectivity (`last_online_at` within 15 min) while the Diagnostics table showed each row's stored `device_diagnostics.online` (always true for device-initiated samples) — two different sources. |
| 4 | **Empty panels** | No demo data — every role landed on empty tables. |

## What was fixed

**1 — UI permission gating (the Operator bug).** Replaced **every** `RoleGate` with `PermissionGate` keyed to
the actual permission:
- `RosterSection`: "Sahib təyin et" → `devices.assign`; "Sakin əlavə et" → `residents.create`; "Çıxar" (resident) → `residents.delete`.
- `DeviceActions`: "Redaktə" → `devices.update`; "Aktivləşdir/Bağla" → `devices.disable`; "Köçür" → `devices.transfer`; "Çıxar" → `devices.decommission` (the old single super-admin block was split per action).
- `DevicesPage` "Yeni cihaz" → `devices.create`; `WhitelistTab` resync → `whitelist.manage`; `OrderDetailPage` refund → `refunds.create`.

**2 — Matrix.** Removed `devices.assign` from `technical` (`RolePermissionMatrix`) and reconciled
`role_permissions` on prod (verified `technical` now has 0 `devices.assign`). `techAssignDevice` is now an
operator/super capability; its test was updated accordingly.

**3 — Device status single source of truth.** New `lib/online.ts` `isLive(iso)` (15-min window, mirrors the
backend `offline_threshold_minutes`). The Diagnostics table now computes each row's badge from
`reported_at` recency — so the **latest reading always agrees with the summary**; it can never show
"Online" while the summary says "Offline". (`DeviceAdminResource.online`, `DevicesPage`, `DashboardPage`,
`DeviceDetailPage` all use the same 15-min rule.)

**4 — Demo data.** `DemoDataSeeder` (idempotent): 10 named complexes (Sea Breeze … Yasamal Residence),
24 devices (online/offline mix, **3 in the complex_manager's complex**), 35 residents (owners + members),
23 subscriptions (active + expired), 12 orders (paid/pending/refunded/failed), refunds, 35 whitelist
entries, 50 audit-log rows. *(Apartments / buildings / entrances / vehicles were NOT seeded — those entities
have no schema yet; their RBAC permissions exist and gate the menu, and tables will ship with the feature.)*

---

## Per-role verification (live production, status codes)

✅ = matched expectation. Mutations were probed with empty bodies (403 = no permission, 422 = permission OK
then validation) to avoid side effects; the complex-scope mutations were additionally confirmed with valid
bodies.

### Super Admin — full access ✅
`/devices` 200 · `POST /devices` 422(perm-ok) · `/orders` 200 · `/refunds` 200 · `/subscriptions` 200 ·
`/admins` 200 · `/audit` 200 · `/settings` 200. Sees all 8 sidebar items; all device actions; impersonation.

### Technical — devices only ✅
`/devices` 200 · `POST /devices` 422(create-ok) · `/devices/{id}/diagnostics` 200 ·
`POST .../assign` **403** · `POST .../users` **403** · `/orders` **403** · `/refunds` **403** ·
`/admins` **403** · `/audit` **403** · `/settings` **403**. → No Transfer / Remove / Assign / add-resident.

### Operator — residents + assign + whitelist ✅ (the reported bug)
`/devices` 200 · `POST .../assign` **422**(assign-ok) · `POST .../users` **422**(add-resident-ok) ·
`POST .../whitelist/resync` **202** · `POST /devices` **403**(no create) ·
`/devices/{id}/diagnostics` **403** · `/orders` **403** · `/admins` **403**.
→ **"Sahib təyin et", "Sakin əlavə et", "Çıxar" now visible and working for Operator.**

### Finance — money only ✅
`/devices` **403** · `/orders` 200 · `/refunds` 200 · `/subscriptions` 200 · `/admins` **403** ·
`/settings` **403** · `/devices/{id}/diagnostics` **403**.

### Support — read-only ✅
`/devices` 200 · `/orders` 200 · `/subscriptions` 200 · `/refunds` **403** · `POST .../users` **403** ·
`POST /devices` **403** · `/admins` **403** · `/devices/{id}/diagnostics` **403**. → No mutations, no delete.

### Complex Manager — own complex only ✅
- Sees **only its complex's devices** (3 of 24); list filtered.
- `GET /devices/{other-complex}` → **404**; `GET /devices/{own-complex}` → **200**.
- `POST .../users` on own-complex device (valid) → **201**; on another complex → **404**.
- `POST .../whitelist/resync` own → **202**; other complex → **404**.
- `POST /devices` **403** · `/orders` **403** · `/admins` **403** · `/devices/{id}/diagnostics` **403**.

**Result: 49/49 endpoint checks matched** (the one initial mismatch was a test-method artifact — an empty-body
cross-complex POST returns 422 from validation before the controller's 404 scope check; with a real/valid
body it correctly returns 404, confirmed separately).

## Menu / button verification
- Sidebar items are permission-filtered (`navItems[].permission` + `Sidebar` filter): each role sees only its
  items (Super: 8; Technical: 2; Operator: 2; Finance: 4; Support: 4; Complex Mgr: 2).
- Buttons are `PermissionGate`-gated (above); routes are `RequirePermission`-guarded → unauthorized direct
  navigation renders the **403 page**.
- Backend enforces independently — every endpoint returns 403/404 regardless of the UI (no frontend-only security).

## Device status consistency
Verified on device #1 (last telemetry 363 min ago): summary **Bağlantı: Oflayn**, and the Diagnostics rows
now also render **Oflayn** (computed from each reading's recency vs the same 15-min window). The previous
"summary Offline / diagnostics Online" contradiction is gone — both use `isLive` / `offline_threshold_minutes`.

## Tests & deployment
- Automated suite: **261 passing** (incl. RBAC, `DemoSeederTest`, 2FA-toggle, complex-scope, impersonation).
- Deployed: `RolePermissionMatrix` (technical −assign) + `role_permissions` reconcile; `DemoDataSeeder`;
  rebuilt SPA (`RoleGate`→`PermissionGate`, `isLive`); services reloaded. No schema migration needed.

## Note
A forbidden mutation request sent with an **invalid body** can return 422 (validation) before the controller's
403/404 — the action is always still blocked, and no scope/info leaks. Real clients never hit this (buttons
are hidden; bodies are valid). Documented for completeness.
