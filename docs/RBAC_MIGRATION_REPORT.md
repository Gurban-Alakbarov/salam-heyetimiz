# RBAC — Migration Report

**Date:** 2026-06-27 · What the audit (`RBAC_EXISTING_STATE.md`) found, what was reused, modified, added, and
removed — and why. Nothing existing was recreated; backward compatibility preserved.

## What already existed (and was kept)

| Existing | Kept as-is / extended |
|---|---|
| `AdminRole` enum (`super_admin`, `technical`) + `admin_users.role` column | **Extended** (the 2 preserved) — not recreated |
| JWT admin guard + two-phase 2FA login | Reused unchanged; only a new optional `impersonator` claim added |
| Policy/Gate seam (5 mobile policies) | Untouched (they authorise end-users, not admins) |
| `AuditableEvent` interface + events already implementing it | Reused — they now feed the new audit sink, no re-emit |
| `regions`, resource layer, error-envelope renderer, `PresentsAdminDevice` | Untouched |
| `RoleGate` / `hasRole` (frontend) | Kept (back-compat); generalised with `hasPermission` |

## What was modified

| File | Change | Why |
|---|---|---|
| `AdminRole` enum + `admin_users.role` (migration ALTER) | + operator/finance/support/complex_manager | the 4 required roles (additive enum widen) |
| `admin_users` | + `complex_id` (FK) | complex_manager scope |
| `devices` | + `complex_id` (FK) | scope devices to a complex |
| `AdminUser` model | + `hasPermission/permissionKeys/complexScopeId/isSuperAdmin/complex()` | permission resolution + scope |
| `JwtService` / `JwtRequestGuard` | + `impersonator` claim issue/parse/stash | impersonation |
| `AdminUserResource` (`/me`) | + `permissions[]`, `complex_id`, `impersonator_admin_id`; strict-safe read | frontend gating + impersonation banner |
| 6 admin controllers (Device/DeviceComm/Order/Subscription/Refund/TechDevice) | replaced `ensure*` role checks → `requirePermission(Permission::X)` | permission-based authz |
| `DeviceQuery::adminList` | + `complexId` filter | complex scope on listing |
| 6 mutation FormRequests (`Register/Reconcile/Assign/AddMember/Refund/RefundOrder` + Create/UpdateAdmin) | `authorize()` checks the permission | **403 before** body validation (no 422 leak) |
| `Audit\ModuleServiceProvider` | + wildcard `AuditableEvent` recorder | the audit sink the interface always implied |

## What was added (net-new, additive)

- **Tables:** `complexes`, `permissions`, `role_permissions`, `audit_logs` (migrations `08_rbac/*`).
- **Authorization:** `Permission` catalog (44), `RolePermissionMatrix`, `AuthorizesAdmin` trait.
- **Models:** `Complex`, `Permission`, `RolePermission`, `AuditLog`.
- **Admin controllers/requests:** `AdminManagementController` (CRUD + role assign), `ImpersonationController`
  (start/stop), `ComplexController`, `AuditController`; `Create/UpdateAdminRequest`.
- **Services:** `AuditLogger`.
- **Routes:** `admins` CRUD, `admins/{id}/impersonate`, `auth/stop-impersonation`, `complexes`, `audit`.
- **Seeders:** `PermissionSeeder`, `RolePermissionSeeder`, `ComplexSeeder`, `AdminUserSeeder` (6 demo admins),
  `RbacSeeder`.
- **Frontend:** `permissions.ts`, `PermissionGate`, `RequirePermission`, `ForbiddenPage`, `AdminsPage`,
  `AuditPage`, `ImpersonationBanner`; permission-driven `navItems`/`Sidebar`/routes; `AuthProvider.hasPermission`
  + impersonation.

## What was removed

- The duplicated private `ensureAdmin / ensureTechnical / ensureSuperAdmin` helpers in the admin controllers
  (replaced by the `AuthorizesAdmin` trait). No tables, roles, or migrations were dropped.

## Backward-compatibility notes (deliberate)

- **super_admin** retains every capability. **technical** keeps device create/edit/reconcile/diagnostics/
  whitelist/commands **and `devices.assign`** (field installation pairs create with owner-assign — the existing
  `techAssignDevice` endpoint), so no previously-working technical flow breaks.
- **Intentional tightening per the new matrix:** roster *resident* management (add/remove residents), which the
  pre-RBAC code allowed for `technical`, now belongs to **operator / complex_manager** (`residents.*`). This is
  the requested role redefinition, not a regression — the endpoints still work, for the correct role.
- Existing 241 tests still pass (super-admin paths unaffected); +16 RBAC tests → **257 total**.
