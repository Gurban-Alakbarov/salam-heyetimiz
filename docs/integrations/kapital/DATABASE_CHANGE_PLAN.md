# Kapital / BirPay — Database Change Plan

Good news: the existing schema (orders, order_items, payments, payment_callbacks, payment_logs, refunds,
card_tokens) was designed bank-agnostically and **fits BirPay with only small additive changes**. No table is
dropped; no column is renamed; every change below is **additive + nullable** (zero-downtime, reversible).

Legend: **REQUIRED** (needed for the integration) · **OPTIONAL** (nice-to-have, can defer) · **FUTURE** (only if/when a later capability lands).

## Migrations

| # | Change | Table | Status | Why |
|---|---|---|---|---|
| M1 | add `bank_idempotency_key` (string(40), nullable, unique) | `orders` | **REQUIRED** | one stable `X-Idempotency-Key` per order, reused on create-payment retries |
| M2 | add `approval_code` (string(20), nullable) | `payments` | **REQUIRED** | from `authorizationDetail.approvalCode` |
| M3 | add `bank_refund_id` (string(40), nullable) | `refunds` | **REQUIRED** | BirPay refund `id` (for `GET /v1/refunds/{id}` + dedupe) |
| M4 | add `bank_idempotency_key` (string(40), nullable) | `refunds` | **REQUIRED** | stable key for create-refund retries |
| M5 | add `three_ds` (boolean, nullable) | `payments` | **OPTIONAL** | from `authorizationDetail.threeDsSecure` (else fold into raw_response) |
| M6 | add index on `payments.bank_transaction_id` if absent | `payments` | **OPTIONAL** | faster lookup by RRN (already unique per audit → likely present) |
| M7 | add `refunds.processed_at` (timestamp, nullable) | `refunds` | **OPTIONAL** | settle timestamp (else infer from linked payment) |
| M8 | add `card_tokens.bank_token` / stored-credential columns | `card_tokens` | **FUTURE** | only with a Kapital COF/tokenization agreement (not in V1.3) |
| M9 | recurring schedule table | (new) | **FUTURE** | only with a Kapital MIT/recurring agreement (not in V1.3) |

## NOT changed (already adequate)

- `orders.bank_order_id` string(80) — holds BirPay UUID (36). ✓
- `orders.bank_redirect_url` text — holds `confirmUrl`. ✓
- `orders.amount_minor` int — `amount.value × 100`. ✓
- `orders.status` enum (pending/authorising/paid/failed/cancelled/refunded/partially_refunded/expired). ✓
- `payments.bank_transaction_id` string(80) — holds `authorizationDetail.rrn`. ✓
- `payment_callbacks` (payload_hash + (bank_order_id,bank_status) dedupe). ✓ — `bank_status` now stores BirPay
  status strings; no schema change.
- `payment_logs` (partitioned, encrypted). ✓ — only the ALLOWLIST (PHP) changes, not the schema.
- `refunds`, `order_items`, `card_tokens` columns. ✓

## Enums (PHP — no migration)

- `BankStatus` enum **rewritten** to `pending/succeeded/canceled/waiting_for_capture` (+ a
  `cancelationReason`→OrderStatus helper). These are PHP enums / string columns → **no DB migration**.
- `payment_callbacks.bank_status` + `orders.failed_reason` are string columns → accept the new values directly.

## Settings (no migration)

- `kapital_scope` is a new **catalog** entry; the `settings` table is generic key/value → **no migration**.
- (also add `kapital_ip_allowlist`, `kapital_timeout_seconds`, `kapital_connect_timeout_seconds`,
  `kapital_retries`, `kapital_currency`, `kapital_return_url` — all catalog-only, no migration). See
  `SETTINGS_MAPPING.md`.

## Token cache (no migration)

OAuth token cached in Redis (`Cache`) with TTL < 300 s. No table.

## Summary

- **REQUIRED migrations: 4** (M1–M4) — all additive nullable columns.
- **OPTIONAL: 3** (M5–M7).
- **FUTURE: 2** (M8–M9, gated on a Kapital agreement).

The bulk of the work is **code** (wire layer rewrite), not schema. The domain schema was forward-designed.
