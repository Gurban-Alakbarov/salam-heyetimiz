# Kapital / BirPay — Pre-Coding Checklist

## A. Confirm with Kapital (external — blocks production, not coding)

- [ ] Canonical **hosts** (preprod `preapi.birpay.az`, prod `api.birpay.az`) vs the credential-sheet
      `precheckout.kapitalbank.az` (Cloudflare-blocked). Get it in writing.
- [ ] **Webhook**: register our URL `https://api.salamheyetimiz.com/v1/payments/webhook`; subscribe
      `payment_succeeded`, `payment_canceled`; obtain the **`X-Signature` shared secret**; obtain **source IPs**.
- [ ] Confirm **refund** webhook events exist (else poll-only).
- [ ] Confirm **BANK_CARD + confirmation REDIRECT** returns a web `confirmUrl` (hosted 3-D Secure) + its shape.
- [ ] Whether **merchant_id/terminal_id** are needed in the e-commerce payment body (vs `posDetail`/token).
- [ ] **Production** `client_id`/`client_secret`, live **MID/TID**, **MCC**, merchant display name.
- [ ] **Idempotency** replay window / TTL policy.
- [ ] (Future) **COF / MIT / tokenization** product for save-card & recurring (not in V1.3).

## B. Verified now (sandbox, read-only) ✅

- [x] OAuth client_credentials token works (`preapi.birpay.az/api/oauth2/token`, 300 s JWT, Keycloak).
- [x] Authenticated `GET /v1/payments/{id}` / `GET /v1/refunds/{id}` reachable; error envelope confirmed.
- [x] Full request/response contract captured for create/retrieve/cancel payment + create/retrieve refund.
- [x] Webhook payload + `X-Signature` (Base64 HMAC-SHA256) scheme captured.
- [x] Error codes + statuses + cancellation reasons captured.

## C. Settings module readiness

- [x] Present: enabled, mode, api_base_url, client_id, client_secret(secret), merchant_id, terminal_id,
      webhook_secret(secret), enable_logging, enable_webhook_logging.
- [ ] **ADD** `kapital_scope` (= `email`).
- [ ] Collapse `success_url`/`fail_url`/`cancel_url` → single `return_url`.
- [ ] Retire/repurpose `kapital_checkout_url` (confirmUrl comes from the API).
- [ ] Keep `save_card_enabled` / `recurring_enabled` **dormant** (no V1.3 API).

## D. Build checklist (implementation phase)

- [ ] `BirPayTokenService` (cache < 300 s, re-auth on 401).
- [ ] `BirPayClient` (createPayment / getPayment / cancelPayment / createRefund / getRefund; Bearer;
      `X-Idempotency-Key`; error mapping; 5xx retry).
- [ ] Rewrite `BankStatus` enum + status mapping.
- [ ] Rewrite `KapitalSignature` → Base64 verify-only; `X-Signature` header.
- [ ] Webhook controller (`/v1/payments/webhook`): verify → dedupe → verify-via-GET → 200.
- [ ] Browser `returnUrl` endpoint (reconcile + render/deep-link).
- [ ] Idempotency-key persistence on orders/refunds.
- [ ] Update `PaymentLogger` allowlist to BirPay fields.
- [ ] Reconciliation job for `pending` past `expiresAt`.
- [ ] `FakeBirPayGateway` + feature tests (create/webhook/refund/idempotency/re-auth/errors).
- [ ] Settings wiring (`kapital_scope`, base/creds from Settings).
- [ ] OpenAPI + docs update.
- [ ] Sandbox E2E with test card `4169738886501026 / 369 / 10-25`.
- [ ] Security sign-off → production config → go-live.

## E. Do NOT (scope guards)

- [ ] Do not store PAN/CVV; do not wire `card_tokens`.
- [ ] Do not build unattended recurring charges on V1.3.
- [ ] Do not sign outbound requests (Bearer only).
- [ ] Do not trust webhook/return bodies for state (always GET).
