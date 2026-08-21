# Official object definitions & sample JSON (authoritative paste, 2026-06-28)

Captured by the user directly from the Notion source — this supersedes the auto-extraction for the **Payment
object**, **Refund object**, full **sample JSON**, and the **Get Bearer Token** doc example (which the live
sandbox response corrects — see note at the end).

## Payment object — Fields

| Property | Type | Notes / values |
|---|---|---|
| `id` | string(UUID) | unique payment id |
| `rrn` | long | **internal payment ID** (numeric, e.g. `8176552`) — NOT the card RRN |
| `amount` | object | `{ value, currency }` |
| `type` | enum | `purchase` \| `refund` |
| `status` | enum | `pending` \| `succeeded` \| `canceled` |
| `createdAt` / `expiresAt` | timestamp | ISO-8601 |
| `paid` | boolean | paid by customer |
| `captured` | boolean | always `true` on BIRBANK, M10, QR |
| `settled` | boolean | clearing performed |
| `refunded` | boolean | |
| `description` | string(255) | |
| `paymentMethodData` | object | **request-side**; `{type}`. Response field is `paymentMethod`. |
| `paymentMethodData.type` | enum | object-table: `M10, BIRBANK, BNPL` · create-body: `M10, BIRBANK, BANK_CARD` |
| `confirmation` | object | |
| `confirmation.type` | enum | `QR, MOBILE, REDIRECT` |
| `confirmation.returnUrl` | string(2048) | where the customer is returned after action |
| `confirmation.confirmUrl` | string | **the URL to send the customer to** (web for `redirect`, deep-link for mobile) |
| `confirmation.confirmData` | string | QR payload (qr scenario) |
| `metadata` | object(k,v) | merchant read-only data |
| `cancellationParty` | string | present when canceled *(samples spell it `cancelationParty`, one L)* |
| `cancellationReason` | enum | `expired_on_confirmation, insufficient_funds, canceled_by_merchant, three_ds_verification_failed, expired_on_capture, general_decline` *(samples: `cancelationReason`)* |
| `posDetail` | object | `{merchantId, terminalId, terminalCity}` |
| `authorizationDetail` | object | `{rrn, approvalCode, threeDSecure}` — **card RRN + approval code + 3DS flag** *(sample: `threeDsSecure`)* |

## Payment — full sample JSON

```json
{
  "id": "6b193bde-e009-4bef-aade-938621608c90",
  "rrn": 8176552,
  "amount": { "value": 1, "currency": "azn" },
  "incomeAmount": {},
  "type": "purchase",
  "status": "pending",
  "createdAt": "2025-05-01T11:28:07.577Z",
  "expiresAt": "2025-05-01T11:43:07.577Z",
  "paid": false,
  "captured": true,
  "refunded": false,
  "description": "checkout page payment",
  "paymentMethod": {},
  "confirmation": {
    "type": "redirect",
    "confirmUrl": "https://checkout.kapitalbank.az/v1/payments?paymentId=6b193bde-e009-4bef-aade-938621608c90"
  },
  "metadata": { "orderId": "1aJVtbMyHmtXsFhimMkuD3A6VmG", "customerAccount": "65143151" },
  "merchant": { "id": "f2c988f3-0738-4e10-bb78-4d38f2579179", "externalId": "E1040009", "name": "Checkout Test Merchant", "mcc": "5462" },
  "posDetail": { "merchantId": "E1040009", "terminalId": "E1040009", "terminalCity": "BAKI CITY" },
  "authorizationDetail": { "rrn": "PGDR123455", "approvalCode": "123456", "threeDsSecure": true }
}
```

Key new fields vs the auto-extraction: `incomeAmount`, `merchant.externalId` (= our `E1040009` MID),
`authorizationDetail` (card RRN/approval/3DS), `confirmation.confirmUrl` for `redirect` = a **web URL on
`checkout.kapitalbank.az`** (preprod: `precheckout.kapitalbank.az`).

## Refund object — Fields

`id` (UUID), `originalId` (UUID, original payment), `amount{value,currency}`, `status` (pending/succeeded/canceled),
`createdAt`, `expiresAt`, `description`, `confirmation{type: QR|MOBILE, confirmUrl, confirmData}`.

## Refund — full sample JSON

```json
{
  "id": "0b7c6e10-93d3-4fbb-bb3a-af198390d5d5",
  "originalId": "d52f23e9-3778-4ce0-b02c-651f3307b4c3",
  "rrn": 8176555,
  "amount": { "value": 1.00, "currency": "azn" },
  "incomeAmount": {},
  "type": "refund",
  "status": "succeeded",
  "createdAt": "2025-05-01T12:14:07.526Z",
  "expiresAt": "2025-05-01T12:29:07.526Z",
  "paid": true,
  "paidAt": "2025-05-01T12:14:09.015Z",
  "captured": true,
  "refunded": false,
  "description": "refund",
  "paymentMethod": { "type": "bank_card" },
  "confirmation": {},
  "metadata": {},
  "merchant": { "id": "f2c988f3-...", "externalId": "E1040009", "name": "Checkout Test Merchant", "mcc": "5462" },
  "posDetail": { "merchantId": "E1040009", "terminalId": "E1040009", "terminalCity": "BAKI CITY" }
}
```

The retrieve-refund response is a near-full payment-shaped object (`rrn`, `type:refund`, `paid`, `paidAt`,
`paymentMethod`, `merchant`, `posDetail`) — richer than the minimal Refund Fields table.

## Get Bearer Token — doc example vs LIVE (live wins)

- **Doc example** (200): `{ "accessToken", "refreshToken", "accessExpiresIn": 500, "refreshExpiresIn": 1000 }`.
- **Live sandbox** (200): `{ "access_token", "expires_in": 300, "refresh_expires_in": 0, "token_type": "Bearer",
  "not-before-policy": 0, "scope": "profile email" }` — standard OAuth2 snake_case, **no usable refresh token**,
  **300 s** lifetime. **Implement against the live shape.**

## Doc inconsistencies to handle defensively in code

1. Status-check endpoints: the Test-Cases table writes `POST /v1/payments/{id}` & `POST /v1/refunds/{id}`, but
   the SUPPORTED ENDPOINTS table (authoritative) says **`GET`**. Live confirms **GET**.
2. Field spelling: `cancellationParty`/`cancellationReason` (Fields) vs `cancelationParty`/`cancelationReason`
   (samples, one L). Parse the one-L form (the actual response).
3. `threeDSecure` (Fields) vs `threeDsSecure` (sample). Parse defensively.
4. Cancellation reason case: UPPERCASE in the Cancellation section vs lowercase in the object/JSON. Use lowercase.
5. `paymentMethodData.type`: `BNPL` (object table) vs `BANK_CARD` (create body). Both exist; `BANK_CARD` is the
   card type we use, `BNPL` = instalment.
