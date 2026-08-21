# Kapital / BirPay — Production Setup

> Do not switch to production until the sandbox end-to-end test (create → redirect → 3-D Secure → webhook →
> status) passes and a security review signs off.

## Hosts

| Field | Value |
|---|---|
| API host | `https://api.birpay.az` **[DOC]** |
| Token endpoint | `https://api.birpay.az/api/oauth2/token` **[DOC]** |

Hosted checkout page (where `confirmUrl` points / customer is redirected): `checkout.kapitalbank.az` (prod) /
`precheckout.kapitalbank.az` (preprod) — distinct from the API host; we never call it directly. The credential
sheet's `precheckout.kapitalbank.az` is this checkout-page host, not the API base.

## Required from Kapital before go-live (external dependencies)

1. **Production credentials**: `client_id`, `client_secret` for the production Keycloak realm.
2. **Production `merchant_id` + `terminal_id`** (the live MID/TID; sandbox uses `E1040009`).
3. **Webhook registration**: provide Kapital our webhook URL (`https://api.salamheyetimiz.com/v1/payments/webhook`)
   and the events to subscribe (`payment_succeeded`, `payment_canceled`). Obtain the **shared secret** used to
   compute the `X-Signature` (HMAC-SHA256/Base64).
4. **Webhook source IPs** for our firewall/allowlist (and whether they are stable).
5. **MCC** and merchant display name (`merchant.mcc`, `merchant.name` appear on the payment object).
6. **Confirmation/return URL** allowlist registration if Kapital pins `confirmation.returnUrl` domains.
7. Written confirmation of **card (BANK_CARD) + REDIRECT** hosted 3-D Secure behaviour and the `confirmUrl` shape.
8. If **recurring / stored-card** is ever needed: a separate product/agreement (not in V1.3) — see RECURRING_FLOW.

## Production config (Settings → payments)

```
kapital_enabled          = true
kapital_mode             = production
kapital_api_base_url     = https://api.birpay.az
kapital_client_id        = <prod client_id>
kapital_client_secret    = <prod secret>     (encrypted at rest)
kapital_scope            = email
kapital_merchant_id      = <prod MID>
kapital_terminal_id      = <prod TID>
kapital_webhook_secret   = <prod shared secret>  (encrypted at rest)
```

`success_url` / `fail_url` / `cancel_url` map to a single `confirmation.returnUrl` in this API (the gateway
decides outcome; we reconcile via `GET /v1/payments/{id}`). Keep one canonical return URL.

## Operational requirements

- **TLS only**; bearer token over HTTPS; token cached server-side with TTL < 300 s.
- **Idempotency keys** persisted per order/refund so retries reuse the same key.
- **Webhook signature verification mandatory**; reject on mismatch (see WEBHOOK_FLOW).
- **No PAN/CVV** ever stored or logged (the API is hosted-page/redirect, so card data never touches us).
- **Reconciliation job** for payments left `pending` past `expiresAt` (poll `GET /v1/payments/{id}`).
- Rotate `client_secret` and webhook secret on a schedule (R-SEC-10).
