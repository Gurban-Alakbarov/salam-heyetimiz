# Kapital / BirPay — Admin UI Plan

What the administrator sees. **Existing** = already built (reuse). **NEW** = to add.

## Current state (audited)

- **Sifarişlər** (`/orders`): table `Reference | Purpose | Amount | Status | Created`; status filter; cursor
  pagination; row → detail.
- **Sifariş detalı** (`/orders/:id`): summary (reference, status, purpose, amount, paid/created, failed reason);
  items table; **Yenidən yoxla** (recheck) + **Geri qaytar** (refund, gated `refunds.create`, visible for
  paid/partially_refunded).
- **Geri qaytarmalar** (`/refunds`): table `ID | Order | Amount | Status | Reason | Created`; status filter.
- **Parametrlər → Ödənişlər** (Settings v2): the Kapital config fields + generic **Bağlantını yoxla**.

## Plan

### 1. Payments settings — Settings → Ödənişlər (Existing + extend)
- All target keys from `SETTINGS_MAPPING.md` (add `kapital_scope`, `kapital_ip_allowlist`, timeouts, return URL,
  tunables; remove checkout/fail/cancel URLs).
- **Test Connection** (NEW behaviour): replace the generic HTTP probe with a real **OAuth token fetch** against
  the configured host → shows `✓ token acquired (expires in 300s)` or the exact error; optionally a
  `GET /v1/payments/{random}` → expect `payment_not_found` (proves authenticated reachability). No payment created.
- Read-only **Webhook URL** + **Return URL** displayed for copy → give to Kapital.

### 2. Merchant information (NEW — small card)
- Shows `merchant.externalId` (our MID), `name`, `mcc`, `terminalId`, `mode (sandbox/production)`.
- Source: Settings + the `merchant{}` block echoed on the last payment (cache the latest).

### 3. Webhook status (NEW — card / panel)
- Last webhook received (time, event, payload.id → order link).
- Signature health: count valid vs invalid over 24h (from `payment_logs.signature_valid`).
- Subscribed events + the registered webhook URL.

### 4. Payment logs (NEW — `/payments/logs` viewer)
- Reads `payment_logs` (allowlisted + decrypted server-side): `time | direction (out/in) | endpoint | method |
  http_status | signature_valid | latency_ms | order`.
- Filters: direction, endpoint, signature_valid, order, date range.
- Row → the (already redacted) request/response JSON. Gated by a new `payments.logs.view` permission (super/finance).

### 5. Failed payments (NEW — a filtered Orders view / tab)
- Orders with `status ∈ {failed, expired}` + `failed_reason`, newest first; quick filters by reason
  (3ds_failed, insufficient_funds, callback_timeout, …). Reuses the orders endpoint (status filter exists).

### 6. Failed webhooks (NEW — panel)
- `payment_callbacks` with `outcome ∈ {signature_invalid, unmatched, error}` + `payment_logs` with
  `signature_valid=false`: `time | bank_order_id | bank_status | outcome | ip`. Surfaces tampering / drift /
  orphan callbacks.

### 7. Manual retry (NEW — buttons)
- On an order stuck `pending` (registration failed): **Retry registration** → re-run create-payment (idempotent).
- On an order `authorising` with no outcome: **Recheck** (exists) → `GET /v1/payments/{id}`.
- On a refund `failed`: **Retry refund** → re-run create-refund with the same `bank_idempotency_key`.
- All gated by permission; all audited.

### 8. Manual refund (Existing — keep)
- The existing refund dialog (amount ≤ net captured, reason). Backend now calls `POST /v1/refunds`. Add a
  **full-refund** shortcut (omit amount).

### 9. Transaction detail page (NEW — enrich Order detail)
- Add a **Payments** sub-table: `type | amount | status | RRN (authorizationDetail.rrn) | approval code |
  3DS | occurred_at`.
- Add a **Callbacks/timeline**: each `payment_callbacks` row (bank_status, outcome, time) + key gateway events.
- Add a **Logs** link (filtered to this order).
- Show `bank_order_id`, `confirmUrl`, `bank_idempotency_key`.

### 10. Search & filters (NEW — on Orders)
- Search by **reference**, **bank_order_id (BirPay id)**, **RRN**, **payer phone/id**.
- Filters: status (exists), purpose (exists), date range (exists), amount range (NEW), mode (NEW).
- Backend: extend `adminListOrders` query params; indexes already exist on reference + bank_order_id.

## Permissions

- View orders/refunds: existing `orders.view` / `refunds.view`.
- Create refund / retry: existing `refunds.create`.
- Payment settings: `system.settings.manage` (super only).
- Payment logs (NEW): `payments.logs.view` (super/finance) — add to the RBAC catalog.

## Out of scope (no UI)

- Save card / stored cards / recurring management screens (no V1.3 API) — not built.
