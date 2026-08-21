# Kapital / BirPay — FINAL IMPLEMENTATION SPEC (contract freeze)

Authoritative implementation contract. No code yet. Derived from: the official V1.3 reference + the user's
authoritative paste (`raw/03_official_objects_and_samples.md`), **live sandbox verification**, and a full audit
of the existing Laravel payment domain + admin API/UI + mobile API + Settings. Where the doc and live behaviour
disagree, **live wins**.

Companion docs: `API_REFERENCE.md` (wire contract), `*_FLOW.md`, `DATABASE_CHANGE_PLAN.md`,
`SETTINGS_MAPPING.md`, `ADMIN_UI_PLAN.md`, `MOBILE_FLOW.md`, `IMPLEMENTATION_ORDER.md`.

## 0. Design principle (unchanged)

The payment **domain** (orders/payments/refunds/callbacks/logs, lifecycle, events, R-PAY-04 verify-via-GET,
dedupe, encrypted logging, two-stage admin refund, reconciler) is **reused**. Only the **wire layer** changes
(auth, HTTP client, signature, status enum, webhook payload, field mapping). The existing `OrderService`
orchestration, the mobile `/v1/orders` API shape, and the admin order/refund API shape are **preserved** so the
mobile app and admin UI keep working with minimal change.

## 1. Complete payment lifecycle (web/card)

```
1. Mobile/web → POST /v1/orders (Idempotency-Key)            [EXISTING contract, unchanged]
2. Backend: OrderService.create → pending order + items + reference (SH-YYYYMM-NNNNNN)
3. Backend: BirPayClient.createPayment (X-Idempotency-Key, Bearer)
     body: {amount{value,currency:AZN}, capture:true, paymentMethodData{type:BANK_CARD},
            confirmation{type:REDIRECT, returnUrl}, description, metadata{orderNo:reference}}
4. BirPay → 200 {id, status:pending, confirmation.confirmUrl}
5. Backend: order.bank_order_id=id, order.bank_redirect_url=confirmUrl, status=authorising → OrderAuthorising
6. Client receives bank_redirect_url → redirects customer to the hosted checkout page
7. Customer completes 3-D Secure on checkout.kapitalbank.az (PAN/CVV never touch us)
8. Outcome (any order, treat both as hints, reconcile by GET):
     a. WEBHOOK  POST /v1/payments/webhook  {event, payload.id}  (X-Signature Base64 HMAC)
     b. RETURN   browser → returnUrl
9. Backend (job): GET /v1/payments/{id} → authoritative status → apply
10. succeeded → order.paid + OrderPaid (activates subscription/device);
    canceled → order.failed|cancelled|expired (+ OrderFailed/Cancelled/Expired)
11. Safety net: OrderReconciler (hourly) + ExpireStaleOrders for orders with no outcome
```

## 2. Order lifecycle (state machine — preserved)

`pending` → `authorising` → (`paid` | `failed` | `cancelled` | `expired`) → (`refunded` | `partially_refunded`).

- `pending`: row created, not yet registered with BirPay.
- `authorising`: BirPay payment created, `confirmUrl` issued, awaiting outcome.
- `paid`/`failed`/`cancelled`/`expired`: terminal pre-refund.
- `refunded`/`partially_refunded`: post-refund of a `paid` order.

## 3. Status mapping (BirPay → ours) — FROZEN

### Payment
| BirPay `status` | + `cancelationReason` | → OrderStatus | Payment row |
|---|---|---|---|
| `pending` | — | `authorising` | none |
| `waiting_for_capture` | — | `authorising` (won't occur with `capture:true`) | none |
| `succeeded` | — | `paid` | `Payment{type:charge,status:approved}` |
| `canceled` | `canceled_by_merchant` / `canceled_by_payment_network` | `cancelled` | none |
| `canceled` | `expired_on_confirmation` / `expired_on_capture` | `expired` | none |
| `canceled` | `insufficient_funds` / `three_ds_verification_failed` / `issuer_decline` / `general_decline` | `failed` | `Payment{status:declined}` (optional) |

### Refund
| BirPay refund `status` | → RefundStatus | Effect |
|---|---|---|
| `pending` | `processing` | poll `GET /v1/refunds/{id}` |
| `succeeded` | `approved` | `Payment{type:refund,amount:-x}`; order → `refunded`/`partially_refunded` |
| `canceled` | `failed` | `error_message` set |

## 4. Database mapping (BirPay field → our column)

| BirPay | Column | Notes |
|---|---|---|
| payment `id` (UUID) | `orders.bank_order_id` (string80 ✓ fits 36) | |
| `confirmation.confirmUrl` | `orders.bank_redirect_url` | hosted page |
| our X-Idempotency-Key | `orders.bank_idempotency_key` (**NEW**, uuid) | one per order, reused on retry |
| `amount.value` × 100 | `orders.amount_minor` / `payments.amount_minor` | minor units |
| `authorizationDetail.rrn` | `payments.bank_transaction_id` | the card RRN string |
| `authorizationDetail.approvalCode` | `payments.approval_code` (**NEW**, nullable) | |
| `authorizationDetail.threeDsSecure` | `payments.three_ds` (**NEW**, nullable bool) | optional |
| top-level numeric `rrn` | (store in `payments.raw_response_encrypted`) | internal id, not the RRN |
| refund `id` | `refunds.bank_refund_id` (**NEW**, nullable) | |
| refund `originalId` | (= order.bank_order_id) | cross-check |
| webhook `payload.id` | match → `orders.bank_order_id` → order | |
| webhook raw body | `payment_callbacks.payload_hash` (sha256) | dedupe |
| webhook `payload.status` | `payment_callbacks.bank_status` | hint only |

> All new columns are **additive + nullable** → safe migrations. See `DATABASE_CHANGE_PLAN.md`.

## 5. Every API endpoint (us → BirPay)

| Call | Method | Path | Auth | Idempotency |
|---|---|---|---|---|
| Token | POST | `/api/oauth2/token` (form) | client_credentials | — |
| Create payment | POST | `/v1/payments` | Bearer | `X-Idempotency-Key` |
| Get payment | GET | `/v1/payments/{id}` | Bearer | — |
| Cancel payment | PUT | `/v1/payments/{id}/cancel` | Bearer | — |
| Create refund | POST | `/v1/refunds` | Bearer | `X-Idempotency-Key` |
| Get refund | GET | `/v1/refunds/{id}` | Bearer | — |

Our own surfaces (mobile + admin) are **unchanged** in shape (see audit): `/v1/orders*`,
`/admin/v1/orders*`, `/admin/v1/refunds`. Only the webhook route is renamed `/v1/payments/webhook`
(register with Kapital) — keep `/v1/payments/callback` as an alias during transition if desired.

## 6. Every webhook event

| Event | Action |
|---|---|
| `payment_succeeded` | dedupe → enqueue → `GET /v1/payments/{id}` → if `succeeded` → mark paid |
| `payment_canceled` | dedupe → enqueue → `GET /v1/payments/{id}` → mark failed/cancelled/expired |
| (refund events) | **CONFIRM with Kapital**; until then settle refunds by polling `GET /v1/refunds/{id}` |

Webhook body: `{event, payload:{id, type, paymentMethod, status}}`. We trust only `payload.id` (to locate the
order) and always re-read the gateway. Respond **200** fast.

## 7. Every callback (browser returnUrl)

UX only — never trust for state. Endpoint reconciles via `GET /v1/payments/{id}` and renders success/failed/
pending, then deep-links into the app. The webhook and the return can race; both reconcile idempotently.
See `CALLBACK_FLOW.md`.

## 8. Every redirect flow

- **Web desktop / no Birbank app** → `confirmUrl` is a web 3-D Secure page on `checkout.kapitalbank.az`.
- **Mobile app** → open `confirmUrl` in an in-app browser/WebView; on `returnUrl` close + reconcile.
- **Birbank app present (deep-link)** → `confirmUrl`/`confirmData` is a `birbank://` / `checkout://` deep link.
  For our card flow we use REDIRECT (web URL); other types are future.

## 9. Every error case (see `ERROR_CODES.md`)

Envelope `{id, code, status, method, path, message, timestamp, errors[]}`.
- `bad_request`/`invalid_operation`/`invalid_merchant`/`invalid_policy`/`unauthorized_payment` (4xx) → **permanent**, reconcile by GET, never retry.
- `payment_not_found` (400) → permanent.
- `unauthorized`/`token_expired`/`access_denied` (401/403 auth) → **re-auth once**, retry.
- `unexpected_payment_error`/`internal_server_error`/`bad_gateway`/`gateway_timeout` (5xx) → **retry** with same idempotency key.
- Network timeout → retry (idempotency-safe).

## 10. Retry strategy

| Layer | Policy |
|---|---|
| Token fetch | re-auth on 401; cache token; 1 retry on transient |
| `POST /v1/payments` & `/v1/refunds` | retry on 5xx/timeout, **same `X-Idempotency-Key`**, ≤3 attempts, backoff (e.g. 0.5s,2s,5s); 4xx → stop |
| `GET` status | retry on 5xx/timeout ≤3; cheap |
| Recheck job | existing `RecheckOrderStatusJob` (5 tries, backoff 30s→2h) |
| Inbound webhook | bank retries within an hour; our reconciler is the safety net |

## 11. Idempotency strategy

- **Client→us**: `Idempotency-Key` header on `POST /v1/orders` → `orders.idempotency_key` (existing).
- **Us→BirPay**: `X-Idempotency-Key` (UUID) generated **once per order**, persisted in
  `orders.bank_idempotency_key`, reused for every create-payment retry. Same per refund
  (`refunds.bank_idempotency_key`).
- **Webhook**: `payload_hash = sha256(rawBody)` unique + `(bank_order_id, bank_status)` unique (existing).
- **In-flight guard**: ≤1 authorising/pending order per subscription (R-PAY-11, existing).

## 12. Logging strategy

- `PaymentLogger` reused; **update ALLOWLIST** to BirPay fields:
  `id, originalId, rrn, amount, currency, status, type, paid, captured, settled, refunded, description,
   confirmation, confirmUrl, confirmData, returnUrl, approvalCode, threeDsSecure, code, message, paymentMethod,
   merchant, externalId, cancelationReason`. (Keep `pan`/`cardBrand` in the allowlist defensively — they won't
   appear in the hosted-page model.)
- **Never log** the client_secret, the bearer token, or the webhook secret.
- Outbound logged: token (status only, no token value), create/get/cancel payment, create/get refund.
- Inbound logged: webhook (signature provided/valid, IP, allowlisted payload).
- Encrypted at rest, partitioned monthly (existing). Toggle via `enable_logging` / `enable_webhook_logging`.

## 13. Security strategy (see `KAPITAL_SECURITY_REVIEW.md`)

Token server-side only, TTL<300s; webhook `X-Signature` Base64 HMAC-SHA256 verified on raw bytes
(constant-time) before processing; IP-allowlist the webhook; never trust webhook/return body for state;
no PAN/CVV; encrypted secrets + logs; super-admin-only settings (audited+versioned).

## 14. Timeout strategy

- HTTP request timeout **15 s**, connect **5 s** (from Settings; today in config).
- Token cache TTL **240 s** (< 300 s server lifetime).
- Order `expiresAt` from BirPay (~15 min in samples); our `callback_timeout_minutes` (30) caps local waiting;
  reconciler closes the gap.

## 15. Refund strategy (see `REFUND_FLOW.md`)

Admin two-stage (request → approve → execute) preserved. Execute → `POST /v1/refunds`
`{id: order.bank_order_id, amount: decimal(minor/100) | omit-for-full, description}` + `X-Idempotency-Key`.
Partial + multiple refunds supported; net-captured checks preserved. Settle by webhook (if delivered) or
`GET /v1/refunds/{id}`.

## 15a. Verified create-payment body (live sandbox, 2026-06-28)

The implemented + sandbox-verified body for our web REDIRECT checkout (merchant `E1040009`):

```json
{
  "amount": { "value": 1.0, "currency": "AZN" },
  "capture": true,
  "confirmation": { "type": "REDIRECT", "returnUrl": "https://api.salamheyetimiz.com/v1/payments/return" },
  "description": "Order SH-...",
  "metadata": { "orderNo": "SH-..." },
  "posDetail": { "merchantId": "E1040009", "terminalId": "E1040009" }
}
```

Three live findings now encoded in the client:
1. **`posDetail` is required** for this (POS-configured) merchant — omitting it → `400 bad_request` ("posDetail must not be null").
2. **Omit `paymentMethodData`** for a REDIRECT hosted page — the page offers all methods (incl. card). Pinning `BANK_CARD` with REDIRECT → `400 invalid_operation`.
3. **BirPay may return HTTP 200 with an error envelope** (`{code, status:400, ...}`) — the client treats a body with `code` + `status>=400` as an error regardless of HTTP status.

Response (success): `{ id, status:"pending", confirmation:{ type:"redirect", confirmUrl:"https://precheckout.birpay.az/v1/payments?paymentId=..." } }`. The hosted page host is `precheckout.birpay.az` (preprod) — another member of the checkout-page host family.

## 16. Future compatibility notes

- **Save card / tokenization / recurring**: NOT in V1.3 → out of scope; `card_tokens` + `save_card_enabled` +
  `recurring_enabled` stay **dormant**; auto-renew becomes customer-initiated (reminder + checkout). Revisit
  only with a Kapital COF/MIT agreement.
- **BNPL / instalment (taksit)**: supported via `metadata.instalmentTerms` + `paymentMethodData.type=BNPL` —
  add later without schema change.
- **Two-phase auth/capture**: send `capture:false` → `waiting_for_capture` → a future capture endpoint; not
  needed now.
- **Refund webhooks**: wire when Kapital confirms the events exist.
- **Other payment methods** (M10/BIRBANK/QR deep-links): the `confirmation.type`/`paymentMethodData.type`
  switch already accommodates them.
