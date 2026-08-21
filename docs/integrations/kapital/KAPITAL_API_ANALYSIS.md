# Kapital / BirPay — API Analysis

## What the API actually is

The "Kapital Bank Checkout Merchant API V1.3" is the **BirPay acquiring API**:

- **Identity**: OAuth2 **client_credentials** against **Keycloak** (`presso.kapitalbank.az/realms/acquiring`).
  Bearer JWT, 300 s lifetime, no refresh token. **[VERIFIED live]**
- **Transport**: REST/JSON over HTTPS. Hosts: `preapi.birpay.az` (preprod) / `api.birpay.az` (prod).
- **Model**: **redirect / hosted-page** checkout. We create a payment, redirect the customer to a hosted
  confirmation page (3-D Secure for cards), and learn the result via **webhook** + **`GET /v1/payments/{id}`**.
- **Resources**: `payments` and `refunds` only. **No** tokenization / stored-card / recurring.
- **Idempotency**: `X-Idempotency-Key` header on the two POSTs; safe replay on 5xx.
- **Webhook auth**: `X-Signature` = **Base64(HMAC-SHA256(rawBody, sharedSecret))**.
- **Errors**: uniform envelope `{id, code, status, method, path, message, timestamp, errors[]}`. **[VERIFIED]**

Full contract: `API_REFERENCE.md`. Flows: `PAYMENT_FLOW.md`, `WEBHOOK_FLOW.md`, `CALLBACK_FLOW.md`,
`REFUND_FLOW.md`. Non-features: `SAVE_CARD_FLOW.md`, `RECURRING_FLOW.md`.

## How it differs from what our backend assumed

The current `KapitalBankClient` is a **generic invented contract** (confirmed by inspection):

| Aspect | Current backend (stub) | Real BirPay V1.3 |
|---|---|---|
| Auth | per-request **HMAC** `X-Merchant-Signature` (no token) | **OAuth2 Bearer** (Keycloak client_credentials) |
| Create | `POST /orders` `{merchantId, reference, amount, returnUrl, callbackUrl}` | `POST /v1/payments` `{amount{value,currency}, capture, paymentMethodData, confirmation{type,returnUrl}}` |
| Create resp | `{orderId, redirectUrl}` | `{id, confirmation:{confirmUrl}}` |
| Status | `GET /orders/{id}` → `APPROVED/DECLINED/...` | `GET /v1/payments/{id}` → `pending/succeeded/canceled/waiting_for_capture` |
| Cancel | `POST /orders/{id}/cancel` | `PUT /v1/payments/{id}/cancel` |
| Refund | `POST /orders/{id}/refund` `{amount, idempotencyKey-in-body}` | `POST /v1/refunds` `{id, amount, description}` + `X-Idempotency-Key` header |
| Webhook sig | **hex** HMAC, header `X-Kapital-Signature` | **Base64** HMAC, header `X-Signature` |
| Outbound sig | signs every request | **none** (Bearer only) |
| Webhook body | `{orderId, status, amount, ...}` | `{event, payload:{id, status, type, paymentMethod}}` |
| Idempotency | refund-body field | `X-Idempotency-Key` header (payments + refunds) |
| Save card / recurring | tables + flags | **not in the API** |

## Verified facts (sandbox, 2026-06-28, read-only)

- `POST https://preapi.birpay.az/api/oauth2/token` (form, client_credentials, scope=email) → 200,
  `{access_token (JWT), expires_in:300, token_type:Bearer, scope:"profile email"}`.
- JWT `iss=https://presso.kapitalbank.az/realms/acquiring`, `azp=birpay-test`, 300 s lifetime.
- `GET /v1/payments/{uuid}` authed → 400 `payment_not_found`; unauthed → 401 `unauthorized`.
- `GET /v1/refunds/{uuid}` authed → 400 `payment_not_found`.

## Reusable domain (high)

Our payment **domain** (orders, payments, payment_callbacks, payment_logs, refunds, the order lifecycle,
events, R-PAY-04 verify-via-GET, dedupe, encrypted logging, two-stage admin refunds) is **largely reusable**.
What must change is the **wire layer**: the HTTP client, the auth, the signature, the status enum, the callback
controller. See `KAPITAL_GAP_ANALYSIS_V2.md`.
