# System Settings — Permission Matrix

Settings is **super-admin only**. Every endpoint is gated by the single permission
`system.settings.manage` (`Permission::SYSTEM_SETTINGS_MANAGE`). No other role can read or write settings,
and the **Parametrlər** and **Traccar** menu items are hidden for them (the sidebar filters by permission).

## Endpoints

| Method & path | Route name | Permission | Notes |
|---|---|---|---|
| `GET /admin/v1/settings` | adminGetSettings | `system.settings.manage` | full grouped catalog + current values (secrets masked) + computed read-only URLs |
| `PATCH /admin/v1/settings/{group}` | adminUpdateSettings | `system.settings.manage` | persists one group; encrypts secrets; busts cache; audited (`settings.updated`) |
| `POST /admin/v1/settings/{group}/test` | adminTestSetting | `system.settings.manage` | real connection test (traccar/email/payments) |
| `GET /admin/v1/system/health` | adminSystemHealth | `system.settings.manage` | live health cards |

Unknown `{group}` → `404`. Missing permission → `403` (enforced in the controller before any work).

## Role × Settings access

| Role | View Settings | Edit Settings | System health | Menu visible |
|---|---|---|---|---|
| super_admin | ✅ | ✅ | ✅ | ✅ |
| technical | ❌ | ❌ | ❌ | ❌ |
| operator | ❌ | ❌ | ❌ | ❌ |
| finance | ❌ | ❌ | ❌ | ❌ |
| support | ❌ | ❌ | ❌ | ❌ |
| complex_manager | ❌ | ❌ | ❌ | ❌ |

`system.settings.manage` is granted **only** to `super_admin` in the role matrix
(`app/Domain/Admin/Authorization/RolePermissionMatrix.php`). The dynamic permission engine still applies:
a per-user **grant** of `system.settings.manage` would let a non-super admin in (and a **revoke** would lock a
custom admin out) — but no demo/default account other than super has it.

## Secret handling (verification)

- Secrets (`smtp_password`, `kapital_client_secret`, `kapital_webhook_secret`, `traccar_password`,
  `traccar_api_token`, `traccar_webhook_token`, `sms_api_key`) are stored **encrypted** (`Crypt::encryptString`).
- `GET /settings` returns `''` for every secret + a `secrets_set[key]` boolean. The plaintext is **never** sent.
- A blank secret on `PATCH` keeps the stored value (no accidental wipe).
- Verified by `tests/Feature/Settings/SettingsModuleTest.php`
  ("encrypts secrets, masks them on read, and keeps them on a blank update").

## Audit

Every group save records an `settings.updated` audit event (group + changed keys) via the wildcard
`AuditLogger`, visible under **Audit jurnalı**.

## Tests

`tests/Feature/Settings/SettingsModuleTest.php` (5) + `tests/Feature/Auth/Admin2faToggleTest.php` (3):
catalog read (super 200 / finance 403 / operator PATCH 403), bool/int coercion + immediate effect,
secret encryption/masking/keep-on-blank, unknown-group 404, system health (super 200 / support 403),
and the Require-2FA toggle through the new `security` group.
