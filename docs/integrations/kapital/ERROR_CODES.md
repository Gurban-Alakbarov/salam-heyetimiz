# Kapital / BirPay — Error Codes & Handling

## Error envelope **[VERIFIED]**

Every error returns the same JSON shape:

```json
{
  "id": "ee89482a-4885-4753-8811-aab9e62ef0b7",
  "code": "payment_not_found",
  "status": 400,
  "method": "GET",
  "path": "/v1/payments/11111111-1111-1111-1111-111111111111",
  "message": "Payment with id `...` not found",
  "timestamp": "2026-06-28T15:33:25.777Z",
  "errors": []
}
```

`errors[]` carries field-level validation details, e.g. `[{"property":"amount.value","message":"must not be null"}]`.

## Success codes

| HTTP | Meaning |
|---|---|
| 200 | Request processed |
| 201 | Resource created |

## Error codes

| `code` | HTTP | Meaning | Our handling |
|---|---|---|---|
| `bad_request` | 400 | Missing/invalid parameters; `errors[]` lists fields | Treat as permanent; surface validation; do NOT retry |
| `invalid_operation` | 400 | Action not allowed on the item's current state (e.g. cancel a completed payment, refund an uncaptured payment) | Permanent; reconcile state via GET |
| `invalid_merchant` | 400 | Merchant id not linked to merchant | Config error; alert ops |
| `invalid_policy` | 400 | Merchant not eligible for the action on the resource | Config/agreement error; alert ops |
| `payment_not_found` | 400 | Payment/refund id does not exist **[VERIFIED]** | Permanent; reconcile |
| `unauthorized_payment` | 403 | Fetching a payment not belonging to the merchant | Permanent; security log |
| `access_denied` | 403 | Bearer token invalid | Re-authenticate once, then fail |
| `unauthorized` | 401 | Not authenticated (missing/!valid bearer) **[VERIFIED]** | Re-authenticate, retry once |
| `token_expired` | 401 | Bearer token expired | Re-authenticate, retry once |
| `unexpected_payment_error` | 500 | Internal error | Retryable (idempotency-safe) |
| `internal_server_error` | 500 | Internal error | Retryable |
| `bad_gateway` | 502 | External/upstream error | Retryable |
| `gateway_timeout` | 504 | Internal timeout | Retryable |

## Retry policy (our backend)

- **5xx** (`unexpected_payment_error`, `internal_server_error`, `bad_gateway`, `gateway_timeout`) and network
  timeouts → **retry** the same POST with the **same `X-Idempotency-Key`** (the doc guarantees safe replay).
  Bounded exponential backoff; cap attempts.
- **401 `token_expired` / `access_denied` / `unauthorized`** → re-fetch the bearer token once and retry.
- **400 / 403** → **permanent**; never retry. Reconcile the local order by `GET /v1/payments/{id}`.
- The local order state is driven by `GET /v1/payments/{id}` (authoritative), never by an inbound webhook body
  alone (R-PAY-04 preserved). See `WEBHOOK_FLOW.md`.

## OAuth token errors

`POST /api/oauth2/token` → 401 `{"error":"invalid_request","error_description":"Missing form parameter: <field>"}`.
Standard OAuth2 errors (`invalid_client`, `invalid_scope`) may also occur — treat as config errors.
