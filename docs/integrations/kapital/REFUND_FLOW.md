# Kapital / BirPay — Refund Flow

## Endpoints

- `POST /v1/refunds` — create a refund (idempotent; `X-Idempotency-Key` required).
- `GET /v1/refunds/{id}` — retrieve a refund.

## Create refund

```json
{
  "id": "4f28fcba-81a9-4178-9276-0b38d11dc19e",  // required — ORIGINAL payment id (BirPay payment.id)
  "amount": 4,                                     // optional decimal; omit → FULL refund
  "description": "refund",                         // optional
  "confirmation": { "type": "QR" }                 // optional (QR | MOBILE); omit → refunded directly
}
```

Response 200 (Refund object):

```json
{
  "id": "2d5c25b4-1094-4fba-8d50-e7cddf147845",
  "originalId": "53e53e63-c4c1-4b74-adda-709f8d9b6cce",
  "amount": { "value": 4, "currency": "azn" },
  "status": "pending",
  "createdAt": "...", "expiresAt": "...",
  "description": "refund",
  "confirmation": { "type": "qr", "confirmData": "birbank://v1/refunds?refundId=..." }
}
```

## Rules

- Refund references the **original payment id** via `id` (the refund gets its own `id`, with `originalId`
  pointing back).
- **Partial refunds + multiple refunds** are allowed while the cumulative refunded amount does not exceed the
  payment amount.
- Omitting `amount` performs a **full** refund.
- For card refunds we **omit `confirmation`** → refunded directly (no customer action). `confirmation` is for
  flows that require customer approval (QR/MOBILE wallet refunds).
- The payment must be **completed/captured** first, else `400 invalid_operation`
  ("can't be refunded without completion").

## Status

Refund `status`: `pending` → resolves (`succeeded` / `canceled` per the test-case table). Poll
`GET /v1/refunds/{id}` (and/or a refund webhook if Kapital confirms one) to settle. Map to our `RefundStatus`
(`requested → processing → approved | failed`).

## Mapping to our backend

- Our existing admin two-stage refund (request → approve → execute) stays. The execute step calls
  `POST /v1/refunds` with `{ id: order.bank_payment_id, amount: <minor/100>, description }` and a persisted
  idempotency key (`refund-{refund.id}`).
- On 200 → create a `Payment` row (`type=refund`, negative amount), set `Refund.status` from the gateway,
  transition the order to `refunded` / `partially_refunded` (logic unchanged).
- Idempotency-Key makes retries safe on 5xx.

## Differences vs current stub

- Path is `POST /v1/refunds` with `{id, amount, description}` (not `POST /orders/{id}/refund`).
- Refund amount is **decimal major units** (e.g. `4` or `0.05`), not minor.
- Auth via **Bearer**, idempotency via **`X-Idempotency-Key`** (not an HMAC body field).
