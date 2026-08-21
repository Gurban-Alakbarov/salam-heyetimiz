# RBAC — Architecture

**Date:** 2026-06-27 · Built on the existing authorization seam (see `RBAC_EXISTING_STATE.md`); roles were
extended, permissions/impersonation/audit/complex-scope are additive. Deployed to production.

## Model

```
admin_users.role (enum, 6)  ──▶  role_permissions (role → permission_id)  ──▶  permissions (44, keyed)
        │                                                                            ▲
        │ complex_id (nullable, complex_manager scope)                               │ seeded from
        ▼                                                                  Permission catalog + RolePermissionMatrix (code)
   complexes ──< devices.complex_id
   audit_logs (append-only)   admin token JWT: sub=admin.{id}, role, tfa_verified, [impersonator]
```

**Roles** stay an enum on `admin_users.role` (extended to 6; the 2 originals preserved). **Permissions** are
fully data-driven: `permissions` + `role_permissions`. Adding/repointing a permission is **pure data**
(seed/admin-edit) — authorization logic never changes because every check is permission-based, never
role-equality. Adding a *role* is one enum value + its seeded grants.

## Authorization resolution

`AdminUser::hasPermission($key)`:
1. `super_admin` → `true` (holds the whole catalog).
2. else → the role's keys from `role_permissions ⋈ permissions` (DB-driven), cached per request.
3. **Fallback:** if the `permissions` table is unseeded (e.g. a fresh test DB), resolve from the code
   `RolePermissionMatrix` so the system is never accidentally locked out.

## Enforcement

- **Controller layer** (the project's existing seam): the `AuthorizesAdmin` trait —
  `requirePermission($request, Permission::X)` → 401 if not an admin, **403** if the permission is absent;
  returns the admin for scoping. Replaces the old duplicated `ensureAdmin/ensureTechnical/ensureSuperAdmin`.
- **FormRequest layer** (mutations): `authorize()` checks the permission so **403 precedes body validation**
  (no 422 leak to forbidden users).
- **Complex scope:** `complexScopeId()` + `assertDeviceInScope()` (404 outside scope) + a `complexId` filter
  on `DeviceQuery::adminList` restrict `complex_manager` to its `complex_id`.

## Impersonation

`POST /admin/v1/admins/{id}/impersonate` (perm `admins.impersonate`) issues an admin JWT whose **subject is
the target** (so every permission/scope check applies to the target) plus an `impersonator` claim. The guard
stashes `jwt_impersonator_id` per request; `/me` surfaces it. `POST /admin/v1/auth/stop-impersonation` reads
the claim and re-issues a clean token for the original admin. Both transitions are audited. No nested
impersonation.

## Audit

`audit_logs` (append-only) is fed two ways: (1) a **single wildcard listener** records every domain event
implementing `AuditableEvent` (login, roster, device-assigned/transferred, whitelist, …) — zero per-event
wiring; (2) explicit `AuditLogger::record()` for admin actions not modelled as events (impersonation start/stop,
admin create/update/deactivate). Each entry captures actor (admin/user/system) + impersonator + action +
target + payload + ip/ua.

## Frontend

`/admin/v1/auth/me` returns `permissions[]` + `complex_id` + `impersonator_admin_id`. `AuthProvider` exposes
`hasPermission(...)`; `PermissionGate` hides buttons; `navItems` carry a permission and the sidebar filters by
it; `RequirePermission` guards each route and renders a **403 page** otherwise. Frontend gating is convenience
only — the backend independently enforces every call (no frontend-only security).

## Extensibility

Future permissions: add to `Permission` catalog + `RolePermissionMatrix` + reseed → enforce with one
`requirePermission(...)`. Future roles: add the enum value + seed grants. No change to authorization logic.

## Key files

- `app/Domain/Admin/Authorization/{Permission,RolePermissionMatrix}.php`
- `app/Domain/Admin/Models/{AdminUser,Complex,Permission,RolePermission}.php`
- `app/Domain/Audit/{Services/AuditLogger,Models/AuditLog,ModuleServiceProvider}.php`
- `app/Http/Concerns/AuthorizesAdmin.php`; `app/Http/Resources/AdminUserResource.php`
- `app/Http/Admin/V1/Controllers/Admins/{AdminManagement,Impersonation,Complex}Controller.php`, `Audit/AuditController.php`
- `app/Domain/Auth/Services/JwtService.php` (+ `Guards/JwtRequestGuard.php`) — impersonator claim
- `database/migrations/08_rbac/*` (6); `database/seeders/Rbac/*` (5)
- admin-ui: `auth/{context,AuthProvider,guards,permissions}`, `components/PermissionGate`, `layout/{navItems,Sidebar,ImpersonationBanner}`, `pages/{ForbiddenPage,admins/AdminsPage,audit/AuditPage}`, `App.tsx`
