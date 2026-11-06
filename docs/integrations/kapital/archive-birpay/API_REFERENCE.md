# Kapital / BirPay Checkout Merchant API V1.3 — Complete Reference & Contract

This is the authoritative contract our backend implementation must follow exactly. Items marked **[VERIFIED]**
were confirmed live against the sandbox on 2026-06-28 (read-only, no payments created). Items marked
**[DOC]** come from the official Notion reference. Items marked **[INFER]** are reasoned and must be confirmed
during implementation.

> ⚠️ The "Kapital Bank Checkout Merchant API" is the **BirPay** acquiring API. Identity is **Keycloak**
> (`presso.kapitalbank.az/realms/acquiring`). This is a completely different protocol from the HMAC/`/orders`
> gateway the current backend stubs — see `KAPITAL_GAP_ANALYSIS_V2.md`.

## 1. Hosts

| Environment | API host | Token endpoint | Notes |
|---|---|---|---|
| **Preprod (sandbox)** | `https://preapi.birpay.az` | `https://preapi.birpay.az/api/oauth2/token` | **[VERIFIED]** working |
| **Production** | `https://api.birpay.az` | `https://api.birpay.az/api/oauth2/token` | **[DOC]** (SUPPORTED ENDPOINTS table) |

### Three distinct host families (reconciled with the official samples)

1. **Identity (Keycloak)** — `presso.kapitalbank.az/realms/acquiring` issues the token. We call it via the API
   host's `/api/oauth2/token` proxy. **[VERIFIED]**
2. **Merchant API** — `preapi.birpay.az` (preprod) / `api.birpay.az` (prod). All `/v1/payments` & `/v1/refunds`. **[VERIFIED]**
3. **Hosted checkout page** — `precheckout.kapitalbank.az` (preprod) / `checkout.kapitalbank.az` (prod). This is
   the page the customer is redirected to: the create-payment response's `confirmation.confirmUrl` is
   `https://checkout.kapitalbank.az/v1/payments?paymentId=...` (prod) / `precheckout...` (preprod). **[DOC sample]**

> So the credential sheet's `KAPITAL_PREPROD_BASE_URL=https://precheckout.kapitalbank.az/api` is the **hosted
> checkout page** host (family 3 — where `confirmUrl` points), **not** the API base. The API base for our
> requests is **`preapi.birpay.az`** (family 2). Both are correct; they are different roles. We never call the
> checkout host ourselves — we only redirect the customer to the `confirmUrl` the API returns.

## 2. Authentication — OAuth2 client_credentials (Keycloak) **[VERIFIED]**

```
POST {host}/api/oauth2/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
scope=email
client_id={client_id}
client_secret={client_secret}
```

**200 response** (standard OAuth2 — the doc's `{accessToken,...}` example is simplified; the real body is):

```json
{
  "access_token": "<JWT RS256, ~1085 chars>",
  "expires_in": 300,
  "refresh_expires_in": 0,
  "token_type": "Bearer",
  "not-before-policy": 0,
  "scope": "profile email"
}
```

- Token is a **JWT** (`iss: https://presso.kapitalbank.az/realms/acquiring`, `azp: <client_id>`, `typ: Bearer`).
- **Lifetime: 300 s (5 min)**; `refresh_expires_in: 0` → **no refresh token**. Re-request with
  client_credentials when the cached token nears expiry.
- **401 response**: `{"error":"invalid_request","error_description":"Missing form parameter: <field>"}`.

All subsequent calls send `Authorization: Bearer <access_token>`.

## 3. Endpoints

| Method | Path | Purpose | Idempotency-Key |
|---|---|---|---|
| POST | `/api/oauth2/token` | Get bearer token | — |
| POST | `/v1/payments` | Create payment | **required** |
| GET | `/v1/payments/{id}` | Retrieve payment | — |
| PUT | `/v1/payments/{id}/cancel` | Cancel payment | — |
| POST | `/v1/refunds` | Create refund | **required** |
| GET | `/v1/refunds/{id}` | Retrieve refund | — |

Common headers on calls: `Authorization: Bearer <token>`; on POSTs also
`Content-Type: application/json` and `X-Idempotency-Key: <uuid>`.

## 4. Create payment — `POST /v1/payments` **[DOC]**

Request:

```json
{
  "amount": { "value": 10.0, "currency": "AZN" },        // required (object)
  "capture": true,                                         // required (bool; always true for BIRBANK & QR)
  "description": "payment for order 1235",                // optional, string(255)
  "paymentMethodData": { "type": "BANK_CARD" },           // optional; M10 | BIRBANK | BANK_CARD (omit-able when confirmation=QR)
  "confirmation": { "type": "REDIRECT", "returnUrl": "https://.../return" }, // type req if confirmation present; QR | MOBILE | REDIRECT; returnUrl string(2048)
  "posDetail": { "merchantId": "E1040009", "terminalId": "E1040009" }, // required for POS terminals only
  "metadata": { "orderNo": "1234", "instalmentTerms": "3,6,9" }         // optional read-only k/v (instalmentTerms → taksit)
}
```

**For our web/card checkout**: `paymentMethodData.type = BANK_CARD`, `confirmation.type = REDIRECT`,
`confirmation.returnUrl = <our return URL>`. The response's `confirmation.confirmUrl` is the hosted page we
redirect the customer to. **[CONFIRMED by official sample]** — for `type:"redirect"` the response is
`"confirmation": { "type": "redirect", "confirmUrl": "https://checkout.kapitalbank.az/v1/payments?paymentId=..." }`
(a real web URL; preprod uses `precheckout.kapitalbank.az`). For `mobile`/`qr` the field is a deep-link /
`confirmData`. The Web2App scenario in the doc confirms the redirect-to-confirmUrl flow.

**200 response** (Payment object):

```json
{
  "id": "5b16478f-3d22-46d7-82ed-7182dfd21870",
  "rrn": 34,
  "type": "purchase",
  "amount": { "value": 10.0, "currency": "azn" },
  "status": "pending",
  "createdAt": "2024-07-26T06:03:53.679Z",
  "expiresAt": "2024-07-26T06:33:53.679Z",
  "paid": false, "captured": true, "settled": false, "refunded": false,
  "description": "m10 pos payment",
  "paymentMethod": { "type": "birbank" },
  "confirmation": { "type": "qr", "confirmData": "birbank://v1/payments?paymentId=...", "returnUrl": "" },
  "metadata": {},
  "merchant": { "id": "f346ef13-...", "name": "developer", "mcc": "9402" }
}
```

**400 validation**: `{"id","code":"bad_request","status":400,"method":"POST","path":"/v1/payments","message":"Validation error","timestamp","errors":[{"property":"amount.value","message":"must not be null"}]}`.

## 5. Retrieve payment — `GET /v1/payments/{id}` **[VERIFIED error path]**

200 → full Payment object (adds `cancelationParty`, `cancelationReason` when canceled; `confirmation.confirmUrl`).
400 (bad id) → `{"code":"bad_request","message":"The value '...' of parameter 'id' is invalid",...}`.
400 (unknown id, authed) → `{"code":"payment_not_found","message":"Payment with id `...` not found",...}` **[VERIFIED]**.

## 6. Cancel payment — `PUT /v1/payments/{id}/cancel` **[DOC]**

No body. 200 → Payment object with `status:"canceled"`, `cancelationParty:"merchant"`,
`cancelationReason:"canceled_by_merchant"`. 400 if already completed:
`{"code":"invalid_operation","message":"Payment `...` is completed as `canceled`"}`.

## 7. Create refund — `POST /v1/refunds` **[DOC]**

```json
{
  "id": "4f28fcba-...",            // required — id of the ORIGINAL payment
  "amount": 4,                      // optional bigdecimal; omit → full refund
  "description": "refund",          // optional
  "confirmation": { "type": "QR" }  // optional; QR | MOBILE; omit → refunded directly
}
```

- **Partial + multiple refunds supported** while the refundable amount is not exceeded.
- 200 → Refund object: `{"id","originalId","amount":{value,currency},"status":"pending","createdAt","expiresAt","description","confirmation":{"type","confirmData"}}`.
- 400 → e.g. `{"code":"invalid_operation","message":"Payment ... can't be refunded without completion"}`.

## 8. Retrieve refund — `GET /v1/refunds/{id}` **[VERIFIED error path]**

200 → Refund object. 400 unknown → `{"code":"payment_not_found",...}` **[VERIFIED]**.

## 9. Objects **[DOC field tables + samples — full archive in raw/03_official_objects_and_samples.md]**

### Payment object

| field | type | notes |
|---|---|---|
| `id` | string(UUID) | payment id |
| `rrn` | long | **internal numeric payment id** (e.g. `8176552`) — *not* the card RRN |
| `amount` | object | `{value, currency}` |
| `incomeAmount` | object | (present, empty in samples) |
| `type` | enum | `purchase` \| `refund` |
| `status` | enum | `pending` \| `succeeded` \| `canceled` |
| `createdAt` / `expiresAt` | timestamp | |
| `paid` / `captured` / `settled` / `refunded` | boolean | `captured` always true on BIRBANK/M10/QR |
| `description` | string(255) | |
| `paymentMethod` | object | response side `{type}` (request side is `paymentMethodData`) |
| `confirmation` | object | `{type, confirmUrl, confirmData, returnUrl}` |
| `metadata` | object(k,v) | merchant read-only data (e.g. our `orderNo`) |
| `cancelationParty` | string | present when canceled *(Fields table spells `cancellationParty`)* |
| `cancelationReason` | enum | `canceled_by_merchant, expired_on_confirmation, insufficient_funds, three_ds_verification_failed, expired_on_capture, general_decline` (lowercase in JSON) |
| `merchant` | object | `{id, externalId, name, mcc}` — **`externalId` = our merchant id (`E1040009`)** |
| `posDetail` | object | `{merchantId, terminalId, terminalCity}` |
| `authorizationDetail` | object | **`{rrn, approvalCode, threeDsSecure}`** — the card **RRN string + approval code + 3DS flag** |

> **Mapping for our records**: store BirPay `id` → `orders.bank_order_id`; `authorizationDetail.rrn` /
> `authorizationDetail.approvalCode` → our `payments.bank_transaction_id` / approval code; `confirmUrl` →
> `orders.bank_redirect_url`; `merchant.externalId` is our MID echoed back.

### Refund object

`id, originalId (original payment id), amount{value,currency}, status, createdAt, expiresAt, description,
confirmation{type: QR|MOBILE, confirmUrl, confirmData}`. The **retrieve-refund** response is richer (a
payment-shaped object: also `rrn, type:"refund", paid, paidAt, captured, paymentMethod{type}, merchant,
posDetail`).

## 9a. Documentation inconsistencies — handle defensively

1. Status checks: Test-Cases table shows `POST /v1/payments/{id}` & `POST /v1/refunds/{id}`, but the
   authoritative endpoint table + live behaviour are **`GET`**. Use GET.
2. `cancellationParty/Reason` (Fields) vs `cancelationParty/Reason` (JSON, one L) — parse the **one-L** JSON form.
3. `threeDSecure` (Fields) vs `threeDsSecure` (sample) — parse defensively.
4. Cancellation reasons: UPPERCASE in prose vs lowercase in JSON — JSON is lowercase.
5. `paymentMethodData.type`: `BANK_CARD` (create body, our card flow) vs `BNPL` (object table; = instalment).
6. Token response: doc shows `{accessToken, refreshToken, accessExpiresIn}`; **live is** `{access_token,
   expires_in:300, token_type:Bearer, no refresh}` — implement the live shape (§2).

## 10. Payment status values **[DOC]**

| status | meaning |
|---|---|
| `pending` | created; no transaction yet; waiting for customer/client action |
| `succeeded` | merchant completed; payment successful |
| `canceled` | auto-canceled after expiry, or merchant/network canceled |
| `waiting_for_capture` | two-phase (cards only); awaiting capture after authorisation |

Booleans on the object: `paid`, `captured`, `settled`, `refunded`.

## 11. Cancellation **[DOC]**

Party: `CHECKOUT` (scheduler), `MERCHANT`, `PAYMENT_NETWORK`.
Reasons: `CANCELED_BY_MERCHANT`, `CANCELED_BY_PAYMENT_NETWORK`, `EXPIRED_ON_CONFIRMATION`,
`INSUFFICIENT_FUNDS`, `THREE_DS_VERIFICATION_FAILED`, `EXPIRED_ON_CAPTURE`, `ISSUER_DECLINE`, `GENERAL_DECLINE`.

## 12. Idempotency **[DOC]**

`X-Idempotency-Key: <uuid>` is **required** on `POST /v1/payments` and `POST /v1/refunds`. Each POST is
**retryable**: re-sending with the same key after a 5xx is safe (no duplicate operation).

## 13. Webhooks & signature — see `WEBHOOK_FLOW.md`

Events `payment_succeeded` / `payment_canceled`; payload `{event, payload:{id,type,paymentMethod,status}}`;
`X-Signature` = **Base64(HMAC-SHA256(rawBody, sharedSecret))**.

## 14. Errors — see `ERROR_CODES.md`

Envelope: `{ "id", "code", "status", "method", "path", "message", "timestamp", "errors": [] }` **[VERIFIED]**.

## 15. What this API does NOT have (V1.3)

No tokenization / stored-card / save-card / recurring / credential-on-file endpoints. The only resources are
**payments** and **refunds**. See `SAVE_CARD_FLOW.md` and `RECURRING_FLOW.md`.
