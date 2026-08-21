# Kapital / BirPay — Gap Analysis V2 (current backend ↔ official API)

Legend: **MATCH** keep as-is · **REUSE** keep with field tweaks · **REWRITE** replace to real contract ·
**REMOVE** delete/retire · **ADD** new.

## Authentication

| Item | Verdict | Notes |
|---|---|---|
| Per-request HMAC `X-Merchant-Signature` (outbound) | **REMOVE** | Real API uses no outbound signature |
| OAuth2 token service | **ADD** | `POST /api/oauth2/token` client_credentials; cache token server-side (TTL < 300 s); re-auth on 401 |
| `kapital_client_id` / `kapital_client_secret` (Settings) | **REUSE** | now actually used (OAuth), encrypted secret stays |
| `kapital_scope` | **ADD** | new Settings field = `email` |

## Client ID / Secret / Merchant ID / Terminal ID

| Item | Verdict | Notes |
|---|---|---|
| client_id/secret | **REUSE** | drive OAuth (above) |
| merchant_id / terminal_id | **REUSE / VERIFY** | needed in `posDetail` for POS only; for e-commerce the token's `azp` identifies the merchant. Confirm whether our hosted-page flow needs them in the body |

## Checkout flow

| Item | Verdict | Notes |
|---|---|---|
| `Order` model + lifecycle (pending→authorising→paid/…) | **REUSE** | strong; keep |
| `OrderService.create` + events + redirect-to-client | **REUSE** | keep orchestration |
| `KapitalBankClient.registerOrder` (`POST /orders`) | **REWRITE** | → `POST /v1/payments` with `{amount{value,currency}, capture:true, paymentMethodData{type:BANK_CARD}, confirmation{type:REDIRECT,returnUrl}, description, metadata}` |
| Response parse `{orderId, redirectUrl}` | **REWRITE** | → `{id, confirmation.confirmUrl}`; store `id` in `orders.bank_order_id`, `confirmUrl` in `bank_redirect_url` |
| `kapital_checkout_url` (Settings) | **REMOVE/REPURPOSE** | confirmUrl comes from the response |
| `success_url`/`fail_url`/`cancel_url` | **REUSE→1** | collapse to a single `returnUrl` |

## Payment status

| Item | Verdict | Notes |
|---|---|---|
| `getOrderStatus` (`GET /orders/{id}`) | **REWRITE** | → `GET /v1/payments/{id}` |
| `BankStatus` enum (`APPROVED/DECLINED/CANCELED/REFUNDED/PENDING`) | **REWRITE** | → `pending/succeeded/canceled/waiting_for_capture` (+ booleans paid/captured/settled/refunded) |
| Status→OrderStatus mapping + verify-then-apply (R-PAY-04) | **REUSE** | mapping values change; the "GET is authoritative" rule stays |
| `cancel` (`POST /orders/{id}/cancel`) | **REWRITE** | → `PUT /v1/payments/{id}/cancel` |

## Callback / Webhook

| Item | Verdict | Notes |
|---|---|---|
| `CaptureRawBody` middleware | **MATCH** | keep |
| `payment_callbacks` table + payload_hash dedupe + (bank_order_id,bank_status) unique | **REUSE** | keep dedupe; `bank_status` now BirPay status |
| Verify-via-`getOrderStatus` in the job (R-PAY-04) | **REUSE** | keep |
| `KapitalSignature` (hex HMAC, sign+verify) | **REWRITE** | Base64 HMAC, **verify-only**: `base64(hash_hmac('sha256',raw,secret,true))` |
| `VerifyKapitalSignature` header `X-Kapital-Signature` | **REWRITE** | header `X-Signature` |
| Callback body `{orderId,status,...}` | **REWRITE** | webhook body `{event, payload:{id,status,type,paymentMethod}}`; match `payload.id`→order |
| Route `/v1/payments/callback` | **REUSE/RENAME** | register `/v1/payments/webhook` with Kapital; keep handler shape |
| Browser **returnUrl** endpoint | **ADD** | reconcile via GET + render result / deep-link (CALLBACK_FLOW) |
| `kapital_webhook_secret` (Settings) | **REUSE** | = the `X-Signature` shared secret |

## Refunds

| Item | Verdict | Notes |
|---|---|---|
| `RefundService` two-stage + `Refund` model + events | **REUSE** | keep workflow |
| Gateway `refund` (`POST /orders/{id}/refund`, amount major, key-in-body) | **REWRITE** | → `POST /v1/refunds` `{id:paymentId, amount:decimal, description}` + `X-Idempotency-Key` header |
| Partial/multiple refunds | **REUSE** | API supports it; our net-captured checks stay |

## Save Card / Tokenization / Stored Cards / Recurring

| Item | Verdict | Notes |
|---|---|---|
| `card_tokens` table | **KEEP DORMANT** | no API to populate in V1.3 |
| `save_card_enabled` / `recurring_enabled` flags | **KEEP DORMANT** | do not wire |
| Tokenization / recurring code | **REMOVE FROM SCOPE** | not in V1.3 (see SAVE_CARD_FLOW / RECURRING_FLOW); needs separate Kapital agreement |
| Auto-renew | **REWORK** | becomes customer-initiated renewal (reminder + checkout) |

## Idempotency

| Item | Verdict | Notes |
|---|---|---|
| `orders.idempotency_key` (client→us) | **MATCH** | keep |
| `X-Idempotency-Key` (us→bank) on payments+refunds | **ADD** | generate + **persist** per order/refund so retries reuse it |

## Error handling

| Item | Verdict | Notes |
|---|---|---|
| Error envelope mapping `{code,message,errors[]}` | **ADD** | typed mapping (ERROR_CODES) |
| Retry on 5xx (same idempotency key) + re-auth on 401 | **ADD** | bounded backoff; 4xx permanent |
| `PaymentLogger` allowlist + encryption | **REUSE** | update allowlist keys to BirPay fields (`id,rrn,status,amount,confirmation,code,message`) |

## Data / DB

| Table | Verdict | Field tweaks |
|---|---|---|
| `orders` | **REUSE** | `bank_order_id` = BirPay `payment.id`; `bank_redirect_url` = `confirmUrl` |
| `payments` | **REUSE** | `bank_transaction_id` = **`authorizationDetail.rrn`** (card RRN string) or top-level numeric `rrn`; approval code = `authorizationDetail.approvalCode`; `threeDsSecure` flag available; card brand/last4 absent on hosted-page model |
| `payment_callbacks` | **REUSE** | `bank_status` ∈ BirPay statuses |
| `payment_logs` | **REUSE** | endpoint paths change |
| `refunds` | **REUSE** | store BirPay `refund.id`/`originalId` |
| `card_tokens` | **DORMANT** | — |

## Sandbox vs Production

| Item | Verdict | Notes |
|---|---|---|
| `kapital_api_base_url` + `kapital_mode` | **REUSE** | sandbox `https://preapi.birpay.az`, prod `https://api.birpay.az` |
| Token + API same host | **ADD** | token at `{host}/api/oauth2/token`, API at `{host}/v1/...` |

## Net assessment

- **Domain layer ≈ 75–80% reusable** (orders/payments/refunds/callbacks/logs, lifecycle, events, R-PAY-04,
  dedupe, encrypted logging, admin refund workflow).
- **Wire layer ≈ rewrite** (auth, HTTP client, signature, status enum, webhook payload, refund call, errors).
- **Save-card / recurring ≈ out of scope** for V1.3 (no API).

## Settings module — required fields check

Present & correct: `kapital_enabled`, `kapital_mode`, `kapital_api_base_url`, `kapital_client_id`,
`kapital_client_secret`, `kapital_merchant_id`, `kapital_terminal_id`, `kapital_webhook_secret`,
`enable_logging`, `enable_webhook_logging`.

**Missing → ADD**: `kapital_scope` (= `email`).
**Unused → remove/repurpose**: `kapital_checkout_url` (confirmUrl is returned by the API);
`success_url`/`fail_url`/`cancel_url` → collapse to one `return_url`.
**Dormant (keep, do not wire)**: `save_card_enabled`, `recurring_enabled`.
