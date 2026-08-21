# Access Control & Complex Management — Admin Guide

**Date:** 2026-06-27 · For the **Super Admin**. All of this is runtime — no code or deployment.

## Where it is

Two new sidebar items (visible to Super Admin):
- **Giriş nəzarəti** (`/access`) — the dynamic Role + Permission interface.
- **Komplekslər** (`/complexes`) — residential-complex management (the root entity).

## Giriş nəzarəti (Access Control)

Three tabs:

### İstifadəçi icazələri (User Permissions)  ← the main one
1. Pick an admin from the dropdown.
2. You see, per module: each permission with its state — **rol** (inherited from the role), **+ verilib**
   (granted on top), **− alınıb** (revoked). Effective permissions are shown normally; non-effective are
   struck through.
3. Per permission: **Ver** (grant) or **Al** (revoke). **Defaultlara sıfırla** removes all overrides.
4. The change is immediate on the backend; the affected admin sees the new sidebar/buttons **on their next
   login**.

> Example: give one Technical admin `orders.view` → that admin alone gains the Orders page; other Technical
> admins are unaffected. Revoke `refunds.create` from a Finance admin → their refund button disappears, they
> stay Finance.

### Rollar (Roles)
Lists every role with its description, admin count, and default permissions. **Şablonu redaktə et** opens
checkboxes (grouped by module) to change a **role template** — this changes the defaults for **all** admins of
that role (then layered with their personal overrides). The `super_admin` template is fixed (always all).

### İcazələr (Permissions)
The full catalog, grouped by module (Dashboard, Devices, Residents, Whitelist, Finance, Audit, Admins,
Access, Complexes, System, …) — a read-only reference of every permission key.

## Komplekslər (Complex Management)

The **root entity**: every device belongs to a complex; residents surface through their device roster.

- **List** — cards per complex with live stats (devices online/total, residents, managers).
- **Kompleks yarat** — name + code (+ address). `complexes.manage`.
- **Detail** (`/complexes/{id}`) — stat cards, **managers** (assign/remove a complex manager → sets that
  admin's `complex_id`), and the **devices** table (status, online/offline, owner, location).
- **Scope:** a `complex_manager` admin sees **only their own complex** (the list shows just theirs; another
  complex's detail → 404). Super Admin manages everything.

## Permissions behind these screens

| Screen | Permission | Who |
|---|---|---|
| Giriş nəzarəti (all tabs + grant/revoke) | `access.manage` | super_admin only |
| Komplekslər — view/detail | `complexes.view` | super_admin, complex_manager (own only) |
| Komplekslər — create/edit/delete/assign-manager | `complexes.manage` | super_admin |

## Notes

- Deleting a complex is blocked while it still has devices (move them first).
- Changing a role template or a user override never requires a deploy — it is `role_permissions` /
  `user_permissions` data, resolved on every request.
- The deeper physical hierarchy (**buildings → entrances → apartments → parking**) is **not yet modelled**
  (no tables); the Complex module is the root and is ready to host them. See `RBAC_VERIFICATION_REPORT.md` /
  the roadmap note in `USER_PERMISSION_EXAMPLES.md`.
