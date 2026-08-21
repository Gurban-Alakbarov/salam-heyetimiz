# Kapital / BirPay — Security Review

## Authentication & token handling

- OAuth2 **client_credentials** → short-lived (300 s) Bearer JWT, **no refresh token**.
- Store `client_secret` **encrypted at rest** (Settings secret; already encrypted). Never log it, never return
  it to the client.
- Cache the access token **server-side only**, in memory/Redis, with TTL strictly **< `expires_in`** (e.g.
  240 s); never expose it to the browser/app.
- All calls over **TLS**; verify the server certificate (no `verify=false`).
- Re-authenticate on `401 token_expired/access_denied`, then fail closed.

## Webhook integrity (critical)

- Verify `X-Signature` = **Base64(HMAC-SHA256(rawBody, sharedSecret))** with `hash_equals` (constant-time) on
  the **raw bytes** captured before JSON parsing (`CaptureRawBody`). Reject on mismatch.
- **Never trust the webhook body for state**: always reconcile with `GET /v1/payments/{id}` and apply the
  gateway status (R-PAY-04). A forged/stale webhook is then harmless.
- **Dedupe** by `payload_hash = sha256(rawBody)` (+ unique `(payment_id, status)`), so replays are idempotent.
- **IP allowlist** the webhook endpoint to Kapital's source IPs (obtain them); partition metrics by in/out of
  allowlist to distinguish attacks from bank-side drift (R-PAY-07).
- Respond **200** only after the event is safely persisted/enqueued; return fast.

## Idempotency

- `X-Idempotency-Key` (UUID) on `POST /v1/payments` and `POST /v1/refunds`, **persisted** on the order/refund,
  so a retry after a 5xx/timeout reuses the same key → no double charge / double refund.
- One in-flight order per subscription (existing R-PAY-11) prevents concurrent duplicate orders.

## Card data (PCI)

- **Redirect / hosted-page** model → PAN/CVV never touch our servers (R-PAY-01 upheld by design).
- Do **not** persist PAN/CVV anywhere; `card_tokens` stays dormant (no tokenization API in V1.3).
- `PaymentLogger` keeps the **allowlist + app-layer encryption** of request/response bodies (no silent PAN
  leak); update the allowlist to BirPay fields only.

## Transport & data at rest

- Settings secrets (`kapital_client_secret`, `kapital_webhook_secret`) encrypted (AES via app key) — already so.
- Payment request/response logs encrypted at rest, allowlisted.
- Rotate `client_secret` and webhook secret on schedule (R-SEC-10); the sandbox secret in `SANDBOX_SETUP.md`
  must be rotated before launch and is non-production.

## Authorization (our side)

- Payment-config changes are **super-admin only** (`system.settings.manage`), audited + versioned (Settings v2).
- Admin-initiated refunds remain two-stage (request → approve → execute), audited, RBAC-gated.

## Error & abuse handling

- Map gateway errors to typed outcomes; **never expose** raw gateway internals to clients.
- 5xx → bounded retry with the same idempotency key; 4xx → permanent, reconcile by GET.
- Rate-limit our create-payment endpoint; validate amount/currency server-side before calling the gateway.

## Residual risks / to confirm

- REDIRECT `confirmUrl` for cards is **[INFER]** — verify in sandbox before exposing to users.
- Webhook source IPs + shared secret must be obtained from Kapital before enabling the webhook in production.
- No unattended (recurring) charge possible in V1.3 → no stored-credential risk surface for now.
