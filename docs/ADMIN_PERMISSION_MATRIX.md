# Admin Permission Matrix — per-role summary

**Updated 2026-10-29.** The full permission × role grid is generated from the code:
[`RBAC_PERMISSION_MATRIX.md`](RBAC_PERMISSION_MATRIX.md) (`php artisan docs:rbac`). This page is the short,
human summary of what each role is for. Role templates can be changed in the panel (Admin icazələri › Rol
şablonları); those edits survive deploys.

| Role | For | Can | Cannot |
|---|---|---|---|
| **Super Admin** | owners | everything | — |
| **Texniki** | device installers | register / edit devices, **bind them to a complex**, **assign the owner**, diagnostics, test open, Traccar reconcile, visitor links (view) | create complexes, assign komendants, disable / decommission / transfer devices, residents, money |
| **Operator** | daily operations | residents (incl. **full account deletion — audited as `resident.deleted`**), device roster and owner, visitor links, notifications, applications (approve a legal one only with `complexes.manage`) | complexes list, money, admins, settings |
| **Maliyyə** | finance | orders, refunds, subscriptions incl. free grant | devices, residents (lists) |
| **Dəstək** | support | read-only: residents, orders, subscriptions, devices, open history, applications | any change |
| **Kompleks Meneceri** (komendant) | one complex | **own complex only**: residents (add / remove / family head), devices view, visitor links, notifications; also from the mobile app | anything outside the complex — permissions served by non-scoped endpoints (orders, refunds, applications, audit, settings, admins …) can never be granted (⊘ in the grid) |

Rules worth knowing (owner decisions 2026-10-29):
- Removing a resident from a device cancels their subscription there (no automatic refund); **moving** them to another
  device keeps it.
- An admin may add anyone to a complex device without complex membership — the admin is responsible.
- An admin may invite a family member **on behalf of the family head** (same rules and e-mail as the app).
- The device whitelist has no hardware effect (access is decided on the server); its permissions are marked unused.
- Device models, regions and SIM operators are managed under **Kataloq** (`catalog.manage`, super admin by default).
