# RBAC — Permission Matrix

**Date:** 2026-06-27 · Source of truth: `App\Domain\Admin\Authorization\Permission` (catalog) +
`RolePermissionMatrix` (assignments), seeded into `permissions` (44) + `role_permissions` (55).
super_admin (**S**) implicitly holds **every** permission. Legend: ✓ = granted.

> **Notifications (RBAC decision D1 — "Variant B"):** `notifications.view` / `notifications.send` are documented in the grid below; the live seed grows to **permissions 46 / role_permissions 60** when the notifications module ships. `notifications.send` = super_admin + Operator (global) + Complex Manager (own-complex scope). `notifications.view` also to Support. Technical / Finance: none.

| Group | Permission | S | Technical | Operator | Finance | Support | Complex Mgr |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| dashboard | `dashboard.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| system | `system.settings.manage` | ✓ | | | | | |
| system | `system.sms.manage` | ✓ | | | | | |
| system | `system.payment.manage` | ✓ | | | | | |
| system | `system.traccar.manage` | ✓ | | | | | |
| admins | `admins.view` | ✓ | | | | | |
| admins | `admins.create` | ✓ | | | | | |
| admins | `admins.update` | ✓ | | | | | |
| admins | `admins.delete` | ✓ | | | | | |
| admins | `admins.assign_roles` | ✓ | | | | | |
| admins | `admins.impersonate` | ✓ | | | | | |
| audit | `audit.view` | ✓ | | | | | |
| devices | `devices.view` | ✓ | ✓ | ✓ | | ✓ | ✓ |
| devices | `devices.create` | ✓ | ✓ | | | | |
| devices | `devices.update` | ✓ | ✓ | | | | |
| devices | `devices.reconcile` | ✓ | ✓ | | | | |
| devices | `devices.disable` | ✓ | | | | | |
| devices | `devices.decommission` | ✓ | | | | | |
| devices | `devices.transfer` | ✓ | | | | | |
| devices | `devices.assign` | ✓ | ✓ | ✓ | | | |
| devices | `barriers.assign` | ✓ | | ✓ | | | ✓ |
| devices | `devices.diagnostics.view` | ✓ | ✓ | | | | |
| devices | `devices.traccar.view` | ✓ | ✓ | | | | |
| whitelist | `whitelist.view` | ✓ | ✓ | ✓ | | | ✓ |
| whitelist | `whitelist.manage` | ✓ | ✓ | ✓ | | | ✓ |
| commands | `commands.view` | ✓ | ✓ | ✓ | | ✓ | |
| commands | `commands.test` | ✓ | ✓ | | | | |
| residents | `residents.view` | ✓ | | ✓ | | ✓ | ✓ |
| residents | `residents.create` | ✓ | | ✓ | | | ✓ |
| residents | `residents.update` | ✓ | | ✓ | | | ✓ |
| residents | `residents.delete` | ✓ | | ✓ | | | ✓ |
| apartments | `apartments.view` | ✓ | | ✓ | | | ✓ |
| apartments | `apartments.manage` | ✓ | | ✓ | | | ✓ |
| vehicles | `vehicles.view` | ✓ | | ✓ | | | |
| vehicles | `vehicles.manage` | ✓ | | ✓ | | | |
| finance | `payments.view` | ✓ | | | ✓ | | |
| finance | `orders.view` | ✓ | | | ✓ | ✓ | |
| finance | `refunds.view` | ✓ | | | ✓ | | |
| finance | `refunds.create` | ✓ | | | ✓ | | |
| finance | `subscriptions.view` | ✓ | | | ✓ | ✓ | |
| finance | `subscriptions.manage` | ✓ | | | ✓ | | |
| finance | `invoices.view` | ✓ | | | ✓ | | |
| finance | `reports.view` | ✓ | | | ✓ | | ✓ |
| support | `support.otp.resend` | ✓ | | | | ✓ | |
| notifications | `notifications.view` | ✓ | | ✓ | | ✓ | ✓ |
| notifications | `notifications.send` | ✓ | | ✓ | | | ✓ |
| **Total grants** | | **46** | **12** | **17** | **9** | **8** | **14** |

## Enforcement status

- **Enforced now** (existing endpoints): all `devices.*`, `whitelist.*`, `commands.view`, `residents.*` (roster
  assign/add/remove), `devices.assign`, `orders.view`, `refunds.*`, `subscriptions.view`, `admins.*`,
  `audit.view`, `dashboard.view`. Each admin endpoint calls `requirePermission(...)` (or the FormRequest
  `authorize()` for mutations) → **403** when absent.
- **Defined + assigned + UI-gated, endpoint ships later** (no endpoint yet): `system.*`, `apartments.*`,
  `vehicles.*`, `payments.view`, `invoices.view`, `reports.view`, `commands.test`, `subscriptions.manage`,
  `barriers.assign`, `notifications.view` / `notifications.send`. When those features are built they check the already-seeded permission — no RBAC change.

## Complex scope

`complex_manager` is additionally restricted to its `complex_id`: device list/detail/roster/whitelist are
filtered to (or 404 outside) its own complex, and **admin notification campaigns resolve their audience only within its own complex** (the scope cannot be bypassed — recipients still dedupe by `user_id`). Other roles are global.

> The same grid (per-role capability view) is in `ADMIN_PERMISSION_MATRIX.md`.
