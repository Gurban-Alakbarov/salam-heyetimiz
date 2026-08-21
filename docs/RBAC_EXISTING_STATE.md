# RBAC — Existing Authorization State (Audit)

**Date:** 2026-06-27
**Method:** direct source audit of the backend authorization stack + the admin SPA. Audit only — no code
changed. This is the mandatory pre-implementation inventory; the gap analysis and migration plan follow in
`RBAC_ARCHITECTURE.md` / `RBAC_MIGRATION_REPORT.md`.

> **Headline:** a *role* system exists (2 roles, enum-on-column, checked by duplicated inline guards). A
> *permission* system does **not** exist. There is no admin RBAC middleware, no permissions tables, no
> audit sink, no impersonation, and no permission/role-driven UI gating. The model must be **extended**, not
> rebuilt — the JWT admin guard, the 2FA flow, the policy seam, and the `AuditableEvent` interface are all
> reusable.

---

## 1. Roles — EXISTS (minimal)

| Where | Value |
|---|---|
| `app/Domain/Admin/Enums/AdminRole.php` | `super_admin`, `technical` **only** |
| `admin_users.role` column | `enum('super_admin','technical')` default `technical` (migration `02_..._create_admin_users_table`) |
| JWT | admin access token carries `role` + `tfa_verified` (`issueAdminAccessToken(id, role)`) |
| `AdminUserResource` (`/admin/v1/auth/me`) | returns `role`; **no permissions field** |

**Missing roles:** `operator`, `finance`, `support`, `complex_manager` (4 of the 6 requested).

## 2. Permissions — DOES NOT EXIST

- No `permissions` table, no `role_permissions` table, no permission enum, no `AdminUser::can()/hasPermission()`.
- Authorization is **role-equality only**.

## 3. Authorization mechanism — inline, duplicated, role-only

Each admin controller re-declares private guards and calls them per action. There is **no central admin
authorization middleware or gate.**

| Controller | Guards used |
|---|---|
| `AdminDeviceController` | `ensureAdmin` (any admin), `ensureTechnical` (technical+super), `ensureSuperAdmin` |
| `AdminDeviceRosterController` | `ensureTechnical` (assign/add/remove residents) |
| `AdminDeviceCommController` | `ensureAdmin` (commands/whitelist/diagnostics read), `ensureTechnical` (resync) |
| `AdminOrderController` | `ensureAdmin`, `ensureSuperAdmin` (refund) |
| `AdminSubscriptionController` | `ensureAdmin` |
| `RefundOrderRequest` | `authorize()` → `role === SuperAdmin` |
| `TechDeviceController` (mobile/technical host) | `ensureTechnical` |

Each guard is `abort_unless($actor instanceof AdminUser && <role test>, 403)`. **~7 controllers duplicate
this logic** — the prime refactor target (replace with permission checks).

## 4. Policies / Gates — exist, but for MOBILE users (not admin RBAC)

`Gate::policy(...)` registers 5 policies, all authorising **end-users**, not admins:
`OrderPolicy`, `RefundPolicy`, `SubscriptionPolicy`, `DevicePolicy`, `OpenCommandPolicy` (owner / active-member
/ can-open checks). **No admin permission gate exists.** The policy seam itself is reusable for admin abilities.

## 5. Middleware — auth only

- `auth:admin` — JWT guard (`JwtRequestGuard`, guard name `admin`).
- `admin.tfa` (`RequireAdminTfaVerified`) — gates state-mutating re-auth routes on `tfa_verified`.
- **No `can:`/permission middleware.** Routes group only on `auth:admin` + `throttle:admin`.

## 6. `admin_users` table — columns

`id, email, password, name, role(enum 2), phone, totp_secret, is_2fa_enabled, is_2fa_enforced_at,
recovery_codes_hashes, recovery_codes_generated_at, password_changed_at, failed_login_count, locked_until,
status(active|suspended|offboarded), preferred_language, last_login_at, last_login_ip, remember_token,
timestamps, created_by_admin_id, updated_by_admin_id`.

- **No permission column, no `complex_id`/scope column.** Roles → permissions and complex-scoping have no home yet.

## 7. Audit logging — interface only, NO SINK

- `App\Support\Audit\AuditableEvent` interface exists; **many domain events implement it**
  (`device.assigned`, `roster.user_added`, `order.paid`, `AdminAuthenticated`, etc.).
- **No `audit_log(s)` table** (migration grep = 0 hits) and **no generic listener** persisting auditable
  events (`RecordAuditFromEvent` was deferred). `app/Domain/Audit/` has only an empty `ModuleServiceProvider`.
- **Net:** auditable *signals* are emitted but **nothing records them.** The audit sink must be built; the
  emit side is already wired for most domain actions.

## 8. Impersonation — DOES NOT EXIST

No login-as / act-as mechanism, no impersonator claim in the JWT, no return path.

## 9. "Complex" entity — DOES NOT EXIST

- There is no `complexes` table and no complex concept. The closest existing grouping is **`regions`**
  (9 Baku districts seeded) referenced by `devices.region_id` + a free-text `devices.location_label`.
- `complex_manager` ("access ONLY his own complex") therefore has **no scope anchor today** — this is the
  single biggest design fork (see `RBAC_ARCHITECTURE.md`): reuse `regions` as the complex unit, or introduce a
  dedicated `complexes` entity + `devices.complex_id` + admin→complex assignment.

## 10. Seeders — no admin seeder

- Lookup seeders exist (operators, device model, 9 regions). **No admin-user seeder** — the production
  super admin was provisioned ad-hoc (`deploy/create_super_admin.php`). Demo role accounts must be seeded.

## 11. Admin SPA — role-only, no menu/route gating

| Piece | State |
|---|---|
| `auth/AuthProvider.tsx` | holds `admin` (with `.role`); exposes `hasRole(...roles)`; `/me` gives role, **no permissions** |
| `components/RoleGate.tsx` | renders children if `hasRole(...)` — **role-based, used only around device roster + a few buttons** |
| `layout/navItems.ts` | **static list** (Dashboard, Devices, Subscriptions, Orders, Refunds) — shown to **every** admin, no filtering |
| `App.tsx` routes | `ProtectedRoute` (auth-only) + `PublicRoute`. **No per-route role/permission guard, no 403 page** |
| Buttons | `DeviceActions`/`RosterSection` use `RoleGate roles={['technical','super_admin']}` (role-based) |

**Net frontend:** any authenticated admin sees the full sidebar and can open every route; only a handful of
buttons are role-gated. No permission concept reaches the client.

## 12. OpenAPI authorization

- Single security scheme: admin bearer JWT. Authorization granularity (which role/permission) is **not**
  modelled in the spec — it lives in controller guards. New permission semantics will be documented in the
  matrix docs; per-endpoint `x-required-permission` can be added as the contract annotation.

---

## What is REUSABLE (do not rebuild)

1. **AdminRole enum + `admin_users.role`** — extend the enum + the column (add 4 roles); keep the 2 existing.
2. **JWT admin guard + 2FA two-phase login** — unchanged; add an `impersonator` claim for impersonation.
3. **Policy/Gate seam** (`Gate::policy`, `Gate::define`) — the natural home for permission abilities.
4. **`AuditableEvent` interface + the events already implementing it** — feed the new audit sink; no re-emit.
5. **`PresentsAdminDevice` / resource layer / error-envelope renderer** — unchanged.
6. **`RoleGate` + `AuthProvider.hasRole`** — generalise to `hasPermission` (keep `hasRole` for back-compat).
7. **`regions`** — candidate scope anchor for `complex_manager` (decision pending).

## What must be ADDED (gap)

- 4 roles; a **permissions + role_permissions** model (seeded, DB-driven → extensible); `AdminUser::hasPermission()`.
- A **`can.permission:<perm>` middleware** (+ optional gate) replacing the duplicated `ensure*` guards.
- **Per-endpoint permission enforcement** across all admin controllers.
- **Audit sink** (`audit_logs` table + generic `AuditableEvent` recorder + explicit admin-action coverage:
  login/logout/impersonation/device/resident/whitelist/ownership/barrier/settings).
- **Impersonation** (super-admin act-as + one-click return, JWT `impersonator` claim, audited).
- **Complex scoping** for `complex_manager` (pending the entity decision).
- **SPA**: `/me` → `permissions[]`; `AuthProvider.hasPermission`; `PermissionGate`; permission-filtered
  `navItems`; per-route guards + a **403 page**.
- **Seeders**: roles, permissions, role→permission assignments, **6 demo admin accounts**.
- **Tests**: permission, 403, route-protection, menu-visibility, impersonation, audit.

> No existing role, table, or migration will be recreated. Roles are added to the existing enum/column;
> permissions/audit/impersonation are net-new and additive; the duplicated inline guards are refactored to the
> new permission middleware while preserving identical (or stricter) access for `super_admin`/`technical`.
