# Kapital / BirPay — Payment Flow (web / card checkout)

The V1.3 Checkout API is **redirect-based**: we create a payment, redirect the customer to a hosted
confirmation page, the customer authenticates (3-D Secure for cards), and we learn the outcome via webhook
and/or by polling the payment. Card data never touches our backend.

## Sequence

```
Customer            Our backend                         BirPay API
   |  buy / renew      |                                    |
   |------------------>|                                    |
   |                   | 1. POST /api/oauth2/token  ------> |  (cache bearer, TTL<300s)
   |                   | 2. POST /v1/payments -------------> |
   |                   |    X-Idempotency-Key, Bearer        |
   |                   |    {amount, capture, paymentMethodData:BANK_CARD,
   |                   |     confirmation:{type:REDIRECT, returnUrl}}
   |                   | <--- 200 {id, status:pending, confirmation:{confirmUrl}} ---|
   | 3. 302 confirmUrl |                                    |
   |<------------------|                                    |
   | 4. enters card + 3DS on the hosted page  ------------->|
   |                   |                                    |
   |                   | 5a. WEBHOOK payment_succeeded ----<|  (X-Signature)
   |                   | 5b. customer redirected to returnUrl (browser)
   |                   | 6. GET /v1/payments/{id} (authoritative) --->|
   |                   | <--- {status: succeeded, paid:true} ---|
   |                   | 7. mark order paid → activate subscription/device
```

## Steps

1. **Authenticate** — obtain/reuse a bearer token (`POST /api/oauth2/token`, client_credentials). Cache it
   server-side; lifetime 300 s, no refresh token → re-fetch on near-expiry.
2. **Create payment** — `POST /v1/payments` with a fresh `X-Idempotency-Key` (persist it on our order):
   - `amount.value` (decimal major units, e.g. `10.0`), `amount.currency = "AZN"`.
   - `capture: true` (single-message; immediate capture). Use `capture:false` only if we adopt two-phase
     auth→capture (`waiting_for_capture`) — not needed for our use case.
   - `paymentMethodData.type = "BANK_CARD"`.
   - `confirmation: { type: "REDIRECT", returnUrl: "<our return URL>" }`.
   - `description`, `metadata.orderNo = <our reference>` for correlation.
   - Response: store `id` (the BirPay payment id) on our order; redirect to `confirmation.confirmUrl`. **[INFER:
     confirm REDIRECT returns a web confirmUrl in sandbox before relying on it]**.
3. **Redirect** the customer to `confirmUrl` (the hosted 3-D Secure page).
4. **Customer pays** on the hosted page (PAN/CVV/3DS handled entirely by BirPay).
5. **Outcome arrives two ways** (treat the webhook as a hint, the GET as truth):
   - **Webhook** `payment_succeeded` / `payment_canceled` → see `WEBHOOK_FLOW.md`.
   - **Browser return** to `returnUrl` → see `CALLBACK_FLOW.md` (UX only).
6. **Verify** — always `GET /v1/payments/{id}` and apply *its* `status` (`succeeded` → paid, `canceled` →
   failed/cancelled, `pending` → keep waiting). This preserves R-PAY-04 (gateway status is authoritative).
7. **Apply** — on `succeeded`, mark our order paid and fire `OrderPaid` (activates subscription/device exactly
   as today). On `canceled`, mark failed/cancelled with the `cancelationReason`.

## States

`pending` → (`succeeded` | `canceled`) ; cards may pass through `waiting_for_capture` only if `capture:false`.
A `pending` payment auto-cancels after `expiresAt` (≈30 min in samples) with reason `EXPIRED_ON_CONFIRMATION`.

## Reconciliation

A scheduled job polls `GET /v1/payments/{id}` for orders still `pending`/`authorising` past their `expiresAt`,
and settles them from the gateway status (mirrors the existing `OrderReconciler` / `ExpireStaleOrdersJob`).

## Money & currency

`amount.value` is **major units as a decimal** (e.g. `10.0` = 10.00 AZN). Our store uses `amount_minor`
(integer cents) → convert `minor/100` on the way out and `value*100` on the way in. Currency `"AZN"`.
