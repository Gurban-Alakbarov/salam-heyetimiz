# Kapital / BirPay — Callback (browser return) Flow

"Callback" in this API means the **browser redirect back** to our `confirmation.returnUrl` after the customer
finishes (or abandons) the hosted payment page. It is distinct from the server-to-server **webhook**.

| | Webhook | Callback (returnUrl) |
|---|---|---|
| Channel | server → server (POST) | browser → our site (GET redirect) |
| Trusted for state? | **No** (hint; verify via GET) | **No** (UX only; user-controllable) |
| Signature | `X-Signature` HMAC | none |
| Purpose | notify outcome | return the human to a result page |

## Flow

1. We pass `confirmation.returnUrl` when creating the payment (`POST /v1/payments`).
2. After the hosted page, BirPay redirects the customer's browser to `returnUrl`. The redirect may carry the
   payment id / status as query params **[INFER — confirm exact params in sandbox]**; do not trust them.
3. Our return endpoint:
   - Reads the payment id (from the URL, or from our session/order keyed by `metadata.orderNo`).
   - Calls `GET /v1/payments/{id}` to get the **authoritative** status.
   - Renders the correct result page (success / failed / pending) and, for the mobile app, deep-links back.

## returnUrl design

- A single canonical return URL is sufficient; outcome is decided by the gateway, not by separate
  success/fail/cancel URLs. (Our Settings has `success_url`/`fail_url`/`cancel_url`; map them to one
  `returnUrl` or repurpose `success_url` as the canonical return URL — see gap analysis.)
- For the **mobile app** the return URL can be a universal/app link (e.g. `https://api.salamheyetimiz.com/v1/payments/return`)
  that 302-redirects into the app scheme after reconciling status. Avoid relying on a raw custom scheme as the
  bank `returnUrl` unless Kapital confirms support.

## Race with webhook

The browser return and the webhook can arrive in any order (or the webhook may be delayed/dropped). Because
**both** paths reconcile via `GET /v1/payments/{id}` and our order transition is idempotent, the order settles
correctly regardless of ordering. The reconciliation job covers the case where neither arrives.
