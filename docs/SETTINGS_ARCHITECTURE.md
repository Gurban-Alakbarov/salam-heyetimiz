# System Settings — Architecture

Status: implemented (v1.3). Central, DB-driven configuration for the whole platform. Every value is
editable from the Admin Panel with **no code change and no deploy**; changes take effect immediately.

## Goals

- One place for every system configuration (General, Security, Email, OTP, Payments, Traccar, SMS, Subscriptions, System health).
- Nothing hardcoded — settings come from the database, with config-file defaults as the seed.
- Secrets encrypted at rest, never returned to the client in clear.
- Provider-agnostic Payments and SMS sections (new providers = new catalog entries, no architecture change).
- Super-admin only. Other roles never see the Settings menu or hit the endpoints.

## Components

| Layer | File | Responsibility |
|---|---|---|
| Catalog | `app/Domain/Admin/Settings/SettingsCatalog.php` | Single source of truth: every setting grouped by tab, with `type`, `default`, `label`, `options`, secret flag. Adding a setting = one array entry. |
| Service | `app/Domain/Admin/Services/SettingsService.php` | Read/write, type coercion, **encryption** of secrets, **cache** (busted on every write), grouped & API views, idempotent default seeding. |
| Model | `app/Domain/Admin/Models/Setting.php` | `settings` table row (`key`, `value`, `updated_by_admin_id`). |
| API | `app/Http/Admin/V1/Controllers/Settings/SettingsController.php` | `GET /settings`, `PATCH /settings/{group}`, `POST /settings/{group}/test`. |
| Health | `app/Http/Admin/V1/Controllers/Settings/SystemHealthController.php` | `GET /system/health` — live cards (real checks). |
| Seeder | `database/seeders/Rbac/SettingsSeeder.php` | Seeds catalog defaults (idempotent). |
| UI | `admin-ui/src/pages/settings/SettingsPage.tsx` | Generic, catalog-driven tabs; secrets masked; Save/Test per group; System health cards. |

## Key → storage model

A setting's storage key is `"{group}.{key}"` (e.g. `security.require_2fa`, `payments.kapital_client_secret`).
This preserved the pre-existing `security.require_2fa` key the login flow already read, so the global
Require-2FA toggle kept working with zero migration.

## Types & coercion

`string | int | bool | secret | select | text`. On write the service coerces (`bool → '1'/'0'`, `int → (int)`),
on read it casts back. `secret` values are stored **encrypted** (`Crypt::encryptString`) and:

- never returned by the API (always `''`, with a `secrets_set[key]` boolean telling the UI whether one exists);
- a **blank** secret on save **keeps** the existing value (so you don't wipe a secret by re-saving a form);
- decrypted only for internal consumers via `SettingsService::value($group, $key)`.

## Caching & immediate effect

The full `key → value` map is cached (`Cache::rememberForever('settings:map')`) and **busted on every write**
(`set`, `setGroup`, `seedDefaults`). Reads hit the cache; a save busts it so the next read — including the very
next request's login check — sees the new value. No deploy, no cache-clear command needed.

## Test Connection

`POST /admin/v1/settings/{group}/test` runs a **real** check where feasible and returns `{ok, message, meta?}`:

- `traccar` — `GET {api_url}/api/server` with the saved token → reports reachability + version.
- `email` — TCP connect to `{smtp_host}:{smtp_port}`.
- `payments` — HTTP probe of `{kapital_api_base_url}`.

These never fake success; an unconfigured group returns `ok:false` with a clear reason.

## System health (`GET /admin/v1/system/health`)

Live cards, all real: Laravel/PHP/env, DB ping, Redis ping, queue driver + failed-job count, Horizon installed,
disk usage, PHP memory + limit, CPU load (1m), SMTP configured, payment enabled/configured, Cloudflare proxied
(CF-RAY), and Traccar status (connected/offline + version + device count + queue size + last webhook).

## Extending — adding a provider (no architecture change)

1. Add the provider's fields to the relevant group in `SettingsCatalog` (mark secrets `'type' => 'secret'`).
2. (Payments/SMS) add the provider value to the `provider`/`mode` select `options`.
3. The API, seeding, encryption, and the generic UI pick it up automatically. Wire the runtime client to read
   from `SettingsService::value(...)` when the integration is built.

## Reuse (audit outcome)

Nothing was thrown away. Defaults were seeded from the existing `config/*` values:
`config/domain/auth.php` (JWT/OTP/refresh/lockout), `config/integrations/traccar.php`,
`config/integrations/kapital.php`, `config/integrations/sms.php`, `config/domain/devices.php`
(offline threshold). The old `security.require_2fa` toggle was folded into Settings → Security with the same key.

See `SETTINGS_DATABASE.md` for the full catalog and `SETTINGS_PERMISSION_MATRIX.md` for access rules.
