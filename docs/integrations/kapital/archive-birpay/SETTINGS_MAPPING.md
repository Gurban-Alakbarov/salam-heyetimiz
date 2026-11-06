# Kapital / BirPay — Settings Mapping (single source of truth)

Goal: **every payment value is read from `SettingsService`** (DB, encrypted secrets, cached, audited,
versioned). The config files + `.env` payment keys are **retired** (kept only as seed defaults, not as a
runtime source). This removes the current duplication.

## Current duplication (the problem)

| Value | Lives in config/.env | Also in Settings catalog | Who reads it today |
|---|---|---|---|
| base url | `config/integrations/kapital.php` `base_url` / `KAPITAL_BASE_URL` | `payments.kapital_api_base_url` | **KapitalBankClient reads config()** |
| merchant id | `merchant_id` / `KAPITAL_MERCHANT_ID` | `payments.kapital_merchant_id` | KapitalBankClient (config) |
| webhook/HMAC secret | `hmac_secret` / `KAPITAL_HMAC_SECRET` | `payments.kapital_webhook_secret` | **VerifyKapitalSignature reads config()** |
| ip allowlist | `ip_allowlist` / `KAPITAL_IP_ALLOWLIST` | — (missing) | VerifyKapitalSignature (config) |
| timeouts/retries | `timeout_seconds`, `connect_timeout_seconds`, `retries` | — (missing) | KapitalBankClient (config) |
| return url | `return_url_scheme` | `payments.success_url` (3-way) | KapitalBankClient (config) |
| currency | `config/domain/payments.php` `currency` | — | OrderService |
| callback timeout | `callback_timeout_minutes` | — | OrderService |
| recheck window | `authorising_recheck_after_minutes` | — | OrderReconciler |
| refund window | `refund_window_days` | — | RefundService |

→ Two of these (base url, webhook secret) are **actively read from `config()`** today; the Settings catalog
copies are **dead** (nothing reads them). The integration must flip everything to read from `SettingsService`.

## Target — the complete payment Settings (group `payments`)

| Setting key | Type | Source today | Action |
|---|---|---|---|
| `kapital_enabled` | bool | Settings | keep |
| `kapital_mode` | select(sandbox/production) | Settings | keep (drives base host) |
| `kapital_api_base_url` | string | config `base_url` | **read from Settings** (sandbox `https://preapi.birpay.az`, prod `https://api.birpay.az`) |
| `kapital_client_id` | string | Settings | keep — now used (OAuth) |
| `kapital_client_secret` | secret | Settings | keep — now used (OAuth) |
| `kapital_scope` | string | — | **ADD** (= `email`) |
| `kapital_merchant_id` | string | config | **read from Settings** (`E1040009`) |
| `kapital_terminal_id` | string | Settings | keep (posDetail / reference) |
| `kapital_merchant_name` | string | Settings | keep (display) |
| `kapital_webhook_secret` | secret | config `hmac_secret` | **read from Settings** (= `X-Signature` shared secret) |
| `kapital_ip_allowlist` | string (csv) | config `ip_allowlist` | **ADD** → read from Settings |
| `kapital_return_url` | string | config `return_url_scheme` | **ADD / repurpose** `success_url` → single canonical return URL |
| `kapital_timeout_seconds` | int | config | **ADD** (default 15) |
| `kapital_connect_timeout_seconds` | int | config | **ADD** (default 5) |
| `kapital_retries` | int | config | **ADD** (default 3) |
| `kapital_currency` | string | config domain | **ADD** (default `AZN`) |
| `kapital_callback_timeout_minutes` | int | config domain | **ADD** (default 30) |
| `kapital_authorising_recheck_minutes` | int | config domain | **ADD** (default 30) |
| `kapital_refund_window_days` | int | config domain | **ADD** (default 365) |
| `enable_logging` | bool | Settings | keep |
| `enable_webhook_logging` | bool | Settings | keep |
| `save_card_enabled` | bool | Settings | keep **dormant** (no V1.3 API) |
| `recurring_enabled` | bool | Settings | keep **dormant** (no V1.3 API) |

Computed read-only (already returned by Settings API): `webhook_url` = `/v1/payments/webhook`,
`callback_url`/`return_url` surface.

## To remove / repurpose

- `payments.kapital_checkout_url` → **remove** (the checkout URL is the per-payment `confirmUrl` from the API).
- `payments.fail_url` / `payments.cancel_url` → **remove**; collapse to one `kapital_return_url`
  (outcome is decided by the gateway + reconciled via GET, not by separate URLs).

## Config/.env cleanup (after wiring)

- `config/integrations/kapital.php` → keep only as **non-authoritative defaults** for first-run seeding, or
  delete. **Nothing reads it at runtime** after the client switches to `SettingsService`.
- `config/domain/payments.php` payment tunables (`currency`, `callback_timeout_minutes`,
  `authorising_recheck_after_minutes`, `refund_window_days`) → move to Settings (above); keep
  `provider`, `one_authorising_order_per_subscription`, `always_verify_with_get_order_status` as code
  invariants (not operator-tunable) **or** also move if the operator should toggle them.
- `.env` / `.env.example` → remove `KAPITAL_BASE_URL`, `KAPITAL_MERCHANT_ID`, `KAPITAL_HMAC_SECRET`,
  `KAPITAL_IP_ALLOWLIST` (secrets now live encrypted in Settings).

## Wiring rule (the contract)

- `BirPayClient`, `BirPayTokenService`, the webhook signature verifier, `OrderService`, `OrderReconciler`,
  `RefundService` all read payment config via **`SettingsService::value('payments', <key>)`** — never `config()`.
- The existing **config bridge** (`SettingsConfigBridge`) may optionally overlay these onto `config('domain.payments.*')`
  so legacy `config()` reads keep working during the transition, but the canonical source is Settings.

## Verification checklist (post-implementation)

- [ ] `grep "config('integrations.kapital"` → **0 hits** at runtime (only seed defaults).
- [ ] `grep "config('domain.payments"` → only invariants remain (or 0).
- [ ] No `KAPITAL_*` in `.env` is required for the app to run.
- [ ] Changing a payment value in the admin Settings UI takes effect with **no deploy** (cache-busted).
- [ ] Secrets (`client_secret`, `webhook_secret`) are encrypted at rest and never returned by the API.
