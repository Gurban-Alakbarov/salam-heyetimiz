# Permission Engine — Dynamic Role + Permission Authorization

**Date:** 2026-06-27 · Status: implemented, tested (266 passing), deployed, verified live on production.

The authorization model is now **dynamic per user**. Roles are only **templates**; the real authorization
layer is each admin's **effective permission set**.

## The formula

```
Effective = (Role default permissions  ∪  per-user GRANTED)  \  per-user REVOKED
```

- **super_admin** is the exception: always the **entire catalog**; grants/revokes do not apply (cannot be
  locked out).
- Two admins with the **same role** can have **different effective permissions**.

## Data model

| Table | Role |
|---|---|
| `permissions` (47) | the catalog — every capability key |
| `role_permissions` | the **role template** (default grants per role) — editable |
| `user_permissions` | **per-admin overrides**: `(admin_user_id, permission_id, effect)` where `effect ∈ {grant, revoke}`; one row per (admin, permission) |

`AdminUser` resolves it:
- `roleDefaultKeys()` — the role template (DB `role_permissions`, code-matrix fallback when unseeded).
- `grantedKeys()` / `revokedKeys()` — the user's overrides.
- `permissionKeys()` — **effective** = `(roleDefaults ∪ granted) \ revoked` (super = all). Cached per request.
- `hasPermission($key)` — the single check used by **every** controller / FormRequest (`AuthorizesAdmin`),
  `/me`, the sidebar, route guards, and button gates.

## Live behaviour — DB only, no deploy

`hasPermission` resolves from the DB on **every request**, so a grant/revoke takes effect on the **backend
immediately**. The SPA caches the effective set from `/admin/v1/auth/me` at login, so the **UI updates on the
admin's next login** (sidebar, routes, buttons all re-derive from `admin.permissions`). No code change, no
deployment — pure data in `user_permissions` (and `role_permissions` for template edits).

## API (super-admin tier — `access.manage`)

| Method | Path | Effect |
|---|---|---|
| GET | `/admin/v1/access/roles` | roles + descriptions + default permissions + admin counts |
| PATCH | `/admin/v1/access/roles/{role}` | edit a role **template** (reconcile `role_permissions`); super template is immutable |
| GET | `/admin/v1/access/permissions` | full catalog grouped by module |
| GET | `/admin/v1/admins/{id}/permissions` | `{ role, inherited, additional, revoked, effective }` |
| POST | `/admin/v1/admins/{id}/permissions/grant` | `{permission}` → add a grant override |
| POST | `/admin/v1/admins/{id}/permissions/revoke` | `{permission}` → add a revoke override |
| POST | `/admin/v1/admins/{id}/permissions/reset` | clear all overrides → back to role defaults |

Every change is written to the **audit log** (`access.permission_grant/revoke`, `access.permissions_reset`,
`access.role_updated`).

## Key files

- `database/migrations/10_access/..._create_user_permissions_table.php`
- `app/Domain/Admin/Models/{UserPermission,AdminUser}.php` (effective resolution)
- `app/Http/Admin/V1/Controllers/Access/AccessControlController.php`
- `app/Domain/Admin/Authorization/Permission.php` (catalog) + `RolePermissionMatrix.php` (templates)
- admin-ui: `api/access.ts`, `pages/access/AccessControlPage.tsx` (Roles / Permissions / User Permissions tabs)

## Verified live (production)

- `technical1` (Technical **+ orders.view, refunds.view**) → opens Finance pages (200); `technical2` (plain
  Technical) → 403. **Same role, different access.**
- `finance1` (Finance **− refunds.create**) → refund forbidden (403); default Finance → allowed.
- `operator1` / `support1` (**+ devices.diagnostics.view**) → Diagnostics 200; defaults → 403.
- super_admin keeps full access even with a revoke override applied.
