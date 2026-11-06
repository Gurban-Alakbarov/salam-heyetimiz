# Kapital / BirPay — Webhook Flow

Webhooks are the server-to-server notification of payment/refund outcome. **They are hints**: our backend
verifies every event with `GET /v1/payments/{id}` before mutating state (R-PAY-04).

## Registration (external dependency)

Before any webhook is delivered, the merchant must give Kapital a **webhook URL** and subscribe to events.
We register: `https://api.salamheyetimiz.com/v1/payments/webhook`. Kapital provides the **shared secret** used
for the signature. Confirm the exact source IP range for allowlisting.

## Available events

- `payment_succeeded`
- `payment_canceled`

(Refund notifications: confirm with Kapital whether refund events are delivered; the doc lists only the two
payment events. Until confirmed, reconcile refunds by polling `GET /v1/refunds/{id}`.)

## Delivery & retries

- Method: **POST**; we must respond **HTTP 200**.
- On a non-200/error, Kapital **retries within an hour**, multiple attempts. After the configured number of
  retries with no 200, the event is **discarded** → our reconciliation job is the safety net.
- Therefore our handler must be **idempotent** and **fast** (verify+enqueue, return 200 quickly).

## Payload

```json
{
  "event": "payment_succeeded",
  "payload": {
    "id": "e469456c-0a53-4c31-bb43-d77ab197f94a",
    "type": "purchase",
    "paymentMethod": "birbank",
    "status": "succeeded"
  }
}
```

`payload.id` is the BirPay payment id → match to our order's stored `id`.

## Signature verification — `X-Signature` (Base64 HMAC-SHA256)

Each request carries an `X-Signature` header. From the official Java sample, verification is:

```
expected = Base64( HMAC_SHA256( key = sharedSecret, message = rawRequestBody ) )
valid    = constantTimeEquals(expected, X-Signature)
```

PHP equivalent for our backend:

```php
$expected = base64_encode(hash_hmac('sha256', $rawBody, $sharedSecret, true)); // true = raw binary
$valid    = hash_equals($expected, $providedSignature);
```

> ⚠️ Two differences from the current backend's `KapitalSignature`:
> 1. **Base64**, not hex (`hash_hmac(..., true)` then `base64_encode`).
> 2. Header is **`X-Signature`**, not `X-Kapital-Signature` / `X-Merchant-Signature`.
> The raw body must be hashed **before** JSON parsing (reuse `CaptureRawBody`).

## Handler contract (our backend)

1. `CaptureRawBody` → capture exact bytes.
2. Verify `X-Signature` (Base64 HMAC-SHA256) with `kapital_webhook_secret`; on mismatch → log + 401/200-drop,
   do not process.
3. Dedupe by `payload_hash = sha256(rawBody)` (reuse `payment_callbacks`).
4. Enqueue a job that calls `GET /v1/payments/{payload.id}` and applies the **gateway** status (not the
   webhook's `status`).
5. Return **200** immediately.

## Outbound (no outbound signature)

Unlike the current stub, the merchant does **not** sign outbound API calls (auth is the Bearer token). The
HMAC signature exists **only** on inbound webhooks. Remove `X-Merchant-Signature` from request construction.
