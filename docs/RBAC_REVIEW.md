# RBAC — Implementation Review

**Date:** 2026-06-27 · Status: **implemented, tested (257 passing), deployed to production, verified
end-to-end.** Audit-first per requirement (`RBAC_EXISTING_STATE.md` → implement gaps → this review).

## Delivered against the requirement

| Requirement | Status |
|---|---|
| 6 roles (super_admin/technical/operator/finance/support/complex_manager) | ✅ enum + migration; existing 2 preserved |
| Permission-based authorization under roles (RBAC + permissions) | ✅ `permissions`(44) + `role_permissions`(55), DB-driven |
| Every backend endpoint verifies permissions | ✅ `requirePermission` (controllers) + `authorize()` (mutations) → 403 |
| Per-role permissions exactly as specified | ✅ `RolePermissionMatrix` (see `RBAC_PERMISSION_MATRIX.md`) |
| Complex manager scoped to own complex | ✅ `complexes` + `complex_id` + scope guards (404 outside) |
| Hide menu/buttons by permission; protect routes; protect API; no frontend-only security | ✅ permission-driven `navItems`/`PermissionGate`/`RequirePermission` + 403 page; backend independently enforces |
| Impersonation (login-as + one-click return) | ✅ JWT impersonator claim, start/stop endpoints, banner, audited |
| Audit log (login/logout/impersonation/device/resident/whitelist/ownership/barrier/settings) | ✅ wildcard `AuditableEvent` recorder + explicit admin-action logging → `audit_logs` |
| Future roles/permissions extensible without app-logic change | ✅ permissions = data; checks never branch on role |
| DB: tables, seed roles/permissions/assignments | ✅ 4 tables + 4 seeders (idempotent) |
| Demo accounts, secure passwords, usable immediately | ✅ 6 accounts seeded → `RBAC_TEST_USERS.md`; full 2-phase login verified on prod |
| Automated tests (auth/permission/403/route/menu/impersonation/audit) | ✅ `RbacAuthorizationTest` (16) + suite green (257) |
| Docs (Architecture, Permission Matrix ×2, Test Users, Review, Migration, Existing-State, Test Scenarios) | ✅ all created |

## Tests (257 passing, 853 assertions)

`tests/Feature/Rbac/RbacAuthorizationTest.php`: permission resolution per role; DB-path matches matrix;
403 enforcement (finance→devices, technical→orders, support→delete); allowed paths; **complex scoping**
(list filtered, cross-complex 404); `/me` permissions; admins create + non-super 403; last-super-admin guard;
impersonation token claims + stop; audit entry written. Plus the existing 241 (unchanged behaviour for
super-admin) — including the `techAssignDevice` flow kept working after granting `devices.assign` to technical.

## Production verification (live)

- 6 migrations applied; role enum widened to 6; `permissions=44, role_permissions=55, complexes=2`; 6 demo
  admins seeded (existing `admin@…` super untouched).
- **Full 2-phase login** as `finance@…` (password → computed TOTP) → token, role=finance, 9 permissions.
- Enforcement over HTTP: `GET /orders` → **200**; `GET /admins` → **403**; `POST /devices` → **403**
  (after the FormRequest `authorize()` fix; was 422 from validation-first).
- Frontend dist deployed; sidebar/routes/buttons permission-driven; impersonation banner + Admins/Audit pages live.

## Known limitations / notes

- **Permissions without an endpoint yet** (`system.*`, `apartments.*`, `vehicles.*`, `invoices.view`,
  `reports.view`, `commands.test`, `subscriptions.manage`, `barriers.assign`) are defined, role-assigned and
  UI-gated; they become enforced automatically when those features ship. No placeholders/TODOs in code.
- **Impersonation can't be asserted via sequential HTTP in tests** (the test app's RequestGuard caches the
  user across requests — a documented harness quirk); verified instead at the JWT-claims level + first-request
  stop. Works normally over real HTTP (each request re-resolves).
- **Demo accounts are documented-credential** — disable/rotate before launch (`RBAC_TEST_USERS.md` warning).
- The pre-existing unauthenticated-non-JSON `500` (login-route redirect) is unrelated to RBAC and tracked
  separately.

## Recommendation

Go for manual inspection: log in with each account (`RBAC_TEST_USERS.md`) and walk `ADMIN_TEST_SCENARIOS.md`.
Before real launch: deactivate the demo accounts, rotate the shared TOTP secret, and (optionally) add the
deferred-feature endpoints which already have their permissions defined.
