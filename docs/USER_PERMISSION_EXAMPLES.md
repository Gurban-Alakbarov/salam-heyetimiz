# User Permission Examples — verifying that permissions override roles

**Date:** 2026-06-27 · Seeded on production (`PermissionOverrideSeeder`, idempotent). Log in with each and
compare — same role, different effective access. 2FA: same as the other demo accounts (TOTP secret
`JBSWY3DPEHPK3PXP` or a `SALAM-RBAC-000x` recovery code; with **Require-2FA off**, email+password is enough).

## Seeded accounts

| Account | Email | Password | Role | Override | Net effect |
|---|---|---|---|---|---|
| **Technical #1** | `technical1@salamheyetimiz.com` | `Tech1!Salam-RBAC#2026` | technical | **+ `orders.view`, `refunds.view`** | also sees **Sifarişlər + Geri qaytarmalar** (Finance pages) |
| **Technical #2** | `technical2@salamheyetimiz.com` | `Tech2!Salam-RBAC#2026` | technical | none | plain Technical — **no** Finance |
| **Finance #1** | `finance1@salamheyetimiz.com` | `Fin1!Salam-RBAC#2026` | finance | **− `refunds.create`** | still Finance, but **no refund button** |
| **Operator #1** | `operator1@salamheyetimiz.com` | `Oper1!Salam-RBAC#2026` | operator | **+ `devices.diagnostics.view`** | also opens **Diaqnostika** |
| **Support #1** | `support1@salamheyetimiz.com` | `Supp1!Salam-RBAC#2026` | support | **+ `devices.diagnostics.view`** | read-only Support **+ Diagnostics** |

## What to check (UI + API)

| Compare | Expectation | Verified live |
|---|---|---|
| Technical #1 vs #2 → `/orders`, `/refunds` | #1 = **200** (menu + page visible), #2 = **403** (hidden) | ✅ 200/200 vs 403/403 |
| Finance #1 vs default Finance → refund a paid order | #1 = **403** (button gone), default = allowed (422 perm-ok) | ✅ 403 vs 422 |
| Operator #1 vs default Operator → device Diagnostics | #1 = **200**, default = **403** | ✅ 200 vs 403 |
| Support #1 → device Diagnostics | **200** (granted on top of read-only) | ✅ 200 |
| super_admin with a revoke applied | still **all** (cannot be locked out) | ✅ |

So `technical1` and `technical2` are the **same role** yet have **different access** — proving the override
layer is the real authorization, not the role.

## How to reproduce / manage from the panel

1. Log in as Super Admin → **Giriş nəzarəti → İstifadəçi icazələri**.
2. Pick `technical2@…` → press **Ver** on `orders.view` → that admin now also has Orders (after their next
   login). Press **Al** to remove it. **Defaultlara sıfırla** clears all overrides.
3. Pick `finance@…` → **Al** on `refunds.create` → their refund button disappears.

Every grant/revoke/reset is recorded in **Audit jurnalı** (`access.permission_grant` / `revoke` / reset).

## Roadmap note — Complex physical hierarchy

The **Complex Management** module (Komplekslər) is live: create/edit/delete complexes, assign complex
managers, and view each complex's devices/residents/stats with manager scoping. The **deeper physical
hierarchy is not yet built** — there are no tables for **Buildings → Entrances → Apartments → Parking** yet
(their RBAC permissions `apartments.*` exist and already gate menus). Implementing them is the next phase:
add `buildings`, `entrances`, `apartments`, `parking_spots` tables (FK up to complex), models, CRUD endpoints,
and UI under the complex detail page. The complex is the ready root for that hierarchy, and `devices.complex_id`
already links every device to its complex.
