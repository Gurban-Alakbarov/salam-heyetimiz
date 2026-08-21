# Kapital Bank Integration — Audit

**Date:** 2026-06-21
**Sources:** existing backend code (batch 05 Payments) · "Save Card Process API" PDF · Kapital Bank
E-commerce Notion (JS-rendered — could not auto-extract; cross-checked against the standard Kapital/BPC
e-commerce API). Audit only — no code.

> **Headline:** the payment **domain architecture is solid (~75%)**, but the **concrete Kapital wire
> protocol is a generic placeholder (~15–20%)** that does **not** match the real Kapital BPC e-commerce API
> (order types, `/api/order`, HPP, Basic Auth, save-card/recurring via `set-src-token`/`exec-tran`). Net
> production-ready Kapital integration ≈ **25–30%**. Details in `KAPITAL_GAP_ANALYSIS.md`.

---

## 1. What exists today (verified in code)

### Domain & persistence (strong)
- **Models/tables:** `orders`, `order_items`, `payments`, `payment_logs` (RANGE-partitioned, allowlisted +
  **encrypted** fields, HIGH-15), `payment_callbacks` (dedupe by `payload_hash`), `refunds`, `card_tokens`.
- **Enums:** OrderStatus, OrderPurpose, PaymentType, PaymentStatus, RefundStatus, BankStatus,
  PaymentCallbackOutcome, PaymentLogDirection.
- **Services:** `OrderService`, `PaymentVerifierService` (**always** consults `getOrderStatus` — R-PAY-04),
  `PaymentCallbackService` (dedupe + cross-check + persist), `RefundService`, `OrderReconciler`,
  `PaymentLogger` (allowlist + encrypt).
- **Jobs/events:** reconcile-orders (hourly), PaymentLogsScannerJob, OrderPaid/Refunded/PartiallyRefunded
  events → Subscriptions activation listeners.
- **Tests:** PaymentCallbackTest, KapitalSignatureTest (part of the 30 payment tests, batch 05).

### Gateway boundary
- **Interface `PaymentGateway`** — 4 methods: `registerOrder`, `getOrderStatus`, `refund`, `cancel`.
- **`KapitalBankClient`** (real HTTP) + **`FakeKapitalGateway`** (test double, bound in testing/`provider=fake`).
- **`KapitalSignature`** — HMAC-SHA256 over **raw bytes**, constant-time verify.

### Callback path (hardened)
- `POST /v1/payments/callback` → `CaptureRawBody` → `VerifyKapitalSignature` (raw-body HMAC, CRIT-07) →
  `KapitalCallbackController` → dedupe → **`getOrderStatus` authoritative cross-check** → persist.
- IP allowlist + payload-hash dedupe + never-trust-the-body-for-state.

### Config (`config/integrations/kapital.php`)
`base_url`, `merchant_id`, `hmac_secret`, `ip_allowlist`, `return_url_scheme` (`salam://payment/return`),
timeouts/retries. **All `KAPITAL_*` env values are currently EMPTY** in production.

## 2. What the code actually sends (the placeholder wire protocol)

| Operation | Current code | Auth |
|---|---|---|
| Register | `POST {base}/orders` `{merchantId, reference, amount, currency, returnUrl, callbackUrl}` → expects `{orderId, redirectUrl}` | header `X-Merchant-Signature: HMAC-SHA256(body)` |
| Status | `GET {base}/orders/{id}` → `{status, amount, transactionId|rrn, pan, cardBrand, approvalCode}` | HMAC header |
| Refund | `POST {base}/orders/{id}/refund` `{amount, idempotencyKey}` | HMAC header |
| Cancel | `POST {base}/orders/{id}/cancel` | HMAC header |
| Callback | inbound `POST /v1/payments/callback` `{orderId, status}` verified by body-HMAC | shared `hmac_secret` |

The code comments explicitly flag this as the **"Phase-0 sandbox-confirmable surface"** — i.e. it was built
to a *generic* gateway shape, pending the real Kapital spec.

## 3. What the real Kapital API looks like (PDF-confirmed + standard BPC)

| Operation | Real Kapital (BPC) | Confirmed by |
|---|---|---|
| Host | sandbox `https://txpgtst.kapitalbank.az`; prod `https://e-commerce.kapitalbank.az` (confirm in Notion) | PDF (`txpgtst…`) |
| Auth | **HTTP Basic** (merchant user + password); each order also returns a **`password`** used for HPP + status | PDF (`set-src-token?password=…`) |
| Create order | `POST /api/order` `{order:{ typeRid, amount, currency, language, description, hppRedirectUrl, hppCofCapturePurposes, aut:{purpose} }}` → `{order:{ id, password, hppUrl, status }}` | PDF |
| Order types (`typeRid`) | `Order_SMS` (purchase+capture), `Order_DMS` (auth then capture), `Order_REC` (recurring) | PDF (`Order_SMS`, `Order_REC`) |
| Customer payment | redirect to **HPP** (`hppUrl` / `…?id=&password=`) for 3-D Secure | PDF (`hppRedirectUrl`) |
| Get order info/status | `GET /api/order/{id}?password=…` → status, transactions, **`storedId`** (saved token), PAN/RRN | standard BPC |
| **Save card (COF)** | order with `aut:{purpose:"AddCard"}` + `hppCofCapturePurposes:["Cit","Recurring","UnspecifiedMit"]` → a **`storedId`** token is created | PDF |
| **Recurring / MIT** | `POST /api/order/{id}/set-src-token?password=…` `{order:{initiationEnvKind:"Server"}, token:{storedId}}` → `POST /api/order/{id}/exec-tran` `{tran:{phase:"Single", amount, conditions:{cofUsage:"Cit"\|"Recurring"}}}` | PDF |
| Refund | `exec-tran` refund/return against the captured order (confirm exact in Notion) | standard BPC |
| Reverse/cancel | reverse (same-day void) | standard BPC |
| Callback | primarily **redirect-return + pull-confirm via Get Order Info** (authoritative); a signed S2S webhook is **not** the BPC norm | inference (confirm) |

## 4. Key findings

1. **Wire protocol mismatch (high impact):** endpoints (`/orders` vs `/api/order`), body shape (flat vs
   `{order:{…}}`), auth (`X-Merchant-Signature` HMAC vs **HTTP Basic + order password**), order types
   (none vs `Order_SMS/DMS/REC`) — all differ. The client needs a substantial rewrite to the real API.
2. **Save-card: not implemented (0%).** No `aut.purpose=AddCard`, no `hppCofCapturePurposes`, no `storedId`
   handling. **`card_tokens` has no column to store the Kapital token** (only `pan_masked`, `card_brand`,
   `status`, `revoked_at`).
3. **Recurring/MIT: ~10%.** Subscription auto-renew is scaffolded (`AutoRenewService`, `card_tokens`,
   `card_token_id` on subscription) but **P2-gated and throws `AutoRenewUnavailableException`** — no Kapital
   `set-src-token`/`exec-tran` exists.
4. **Refund: method exists (~40%) but wrong wire format** (`/orders/{id}/refund` vs `exec-tran`).
5. **Callback model likely wrong (~50% done but mis-modelled):** the hardening (raw-HMAC verify, IP
   allowlist, dedupe, authoritative cross-check) is excellent and reusable, but Kapital's confirmation is
   redirect + **Get Order Info pull** (which the app *already* supports via `getOrderStatus` + `recheckOrder`).
   The signed S2S webhook may not exist — verify before relying on it.
6. **HMAC mechanism:** `X-Merchant-Signature` body-HMAC is likely **not** how Kapital authenticates
   (Basic Auth + per-order password) — confirm; the `KapitalSignature` helper may be repurposed only for the
   HPP signature, if any.
7. **No real Kapital terms in the codebase** (`typeRid`, `Order_SMS`, `set-src-token`, `exec-tran`,
   `storedId`, `AddCard`, `cofUsage`, `hppUrl` — all absent) — confirms the integration is generic scaffolding.
8. **Config gaps:** no merchant **username/password** (Basic Auth), no **order-type RIDs**, no HPP fields.

## 5. What is genuinely reusable (don't rebuild)

The **non-wire** layer is high quality and should be kept: order/payment/refund domain model, idempotency,
`payment_logs` (encrypted/allowlisted), `payment_callbacks` dedupe, the **authoritative `getOrderStatus`
cross-check** pattern, reconciliation job, and the `PaymentGateway` seam (extend it, don't replace it).

→ Gap percentages in `KAPITAL_GAP_ANALYSIS.md`; rework plan in `KAPITAL_PRODUCTION_PLAN.md`.
