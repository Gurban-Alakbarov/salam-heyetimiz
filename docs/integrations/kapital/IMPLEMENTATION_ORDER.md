# Kapital / BirPay — Implementation Order (small, independently deployable phases)

Each phase is **additive and gated** (behind `kapital_enabled` and/or a bound `BirPayClient`), so it can ship to
production without changing live behaviour until the switch is flipped. Tests ship with each phase. Hours are
engineering hours (1 senior dev + Opus).

## Phase 0 — Pre-flight (external, blocks production not coding)
Close the dependencies in `KAPITAL_CHECKLIST.md` §A (hosts, webhook registration + secret + IPs, card/REDIRECT
confirmation, prod creds). Run one sandbox create→redirect→3DS→webhook test. *Not engineering hours.*

## Phase 1 — OAuth token service — **~8 h**
- `BirPayTokenService`: client_credentials fetch (`POST /api/oauth2/token`), parse live shape
  (`access_token`, `expires_in`), cache in Redis with TTL < 300 s, re-auth on 401.
- Reads `kapital_api_base_url`, `client_id`, `client_secret`, `scope` from `SettingsService`.
- **Deployable alone**: no caller yet; covered by a unit test + a sandbox token test.
- *Independently deployable*: pure addition, zero effect on existing flows.

## Phase 2 — Payment creation — **~18 h**
- `BirPayClient.createPayment` (Bearer, `X-Idempotency-Key`, body per `API_REFERENCE` §4); response → `id`,
  `confirmation.confirmUrl`.
- Request/response DTOs; error-envelope mapping; 5xx retry with same key.
- Wire `OrderService.create` → call `createPayment`; persist `bank_order_id`, `bank_redirect_url`,
  `bank_idempotency_key`; status → authorising. **Mobile `/v1/orders` contract unchanged.**
- Migrations M1 (orders.bank_idempotency_key).
- Bind the new client behind `kapital_enabled` / provider switch (Fake gateway stays default in tests).
- *Independently deployable*: behind the enable flag; until on, the old path/Fake is used.

## Phase 3 — Redirect + return URL — **~8 h**
- `GET /v1/payments/return` endpoint: reconcile by `GET /v1/payments/{id}`, render result / deep-link to app.
- Confirm the app opens `bank_redirect_url` and detects the return (see `MOBILE_FLOW.md`).
- *Independently deployable*: new route; no effect until payments are created in prod.

## Phase 4 — Webhook — **~12 h**
- `BirPaySignature` (Base64 HMAC-SHA256, verify-only); middleware reads `X-Signature` + `kapital_webhook_secret`
  from Settings; IP allowlist from `kapital_ip_allowlist`.
- Webhook controller `/v1/payments/webhook`: parse `{event, payload.id}`, dedupe (payload_hash), enqueue
  verify-via-GET job, return 200. Keep `/v1/payments/callback` as a transitional alias.
- *Independently deployable*: register the URL with Kapital only when ready; until then it just 200s/dedupes.

## Phase 5 — Status sync — **~10 h**
- `BirPayClient.getPayment` + `cancelPayment`; rewrite `BankStatus` enum + the `status (+cancelationReason) →
  OrderStatus` mapping (spec §3); wire `PaymentVerifierService.verifyAndApply` to the new statuses.
- Update `OrderReconciler` + `RecheckOrderStatusJob` (reuse; new mapping); update `PaymentLogger` allowlist.
- *Independently deployable*: reconciler keeps running; new mapping active only for BirPay orders.

## Phase 6 — Refund — **~10 h**
- `BirPayClient.createRefund` (`{id, amount?, description}` + `X-Idempotency-Key`) + `getRefund`; wire
  `RefundService.executeApprovedRefund`; persist `bank_refund_id`, `bank_idempotency_key`; map refund status.
- Migrations M2–M4 (approval_code, bank_refund_id, refunds.bank_idempotency_key).
- *Independently deployable*: admin refund path; gated by `refunds.create`.

## Phase 7 — Admin UI — **~22 h**
- Settings → Payments fields + real **Test Connection** (token fetch); Merchant info card; Webhook status;
  Payment logs viewer (+ `payments.logs.view` permission); Failed payments + Failed webhooks panels; Manual
  retry buttons; enriched Transaction detail (RRN/approval/3DS, callbacks, logs); search & filters.
- *Independently deployable*: read-only/admin additions; no payment-path risk.

## Phase 8 — Production hardening — **~14 h**
- Sandbox full E2E (test card `4169738886501026/369/10-25`); prod config in Settings; IP allowlist live;
  monitoring/alerts (signature failures, stuck authorising, token errors); OpenAPI + docs; security sign-off;
  flip `kapital_mode=production` + `kapital_enabled=true`.

## Effort summary

| Phase | Hours |
|---|---|
| 1 OAuth | 8 |
| 2 Payment creation | 18 |
| 3 Redirect / return | 8 |
| 4 Webhook | 12 |
| 5 Status sync | 10 |
| 6 Refund | 10 |
| 7 Admin UI | 22 |
| 8 Production hardening | 14 |
| **Subtotal** | **102** |
| Buffer / integration / review (~15%) | ~15 |
| **Total** | **≈ 100–120 h** (≈ 2.5–3 weeks) |

## Production risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | **REDIRECT `confirmUrl` for cards** behaves unexpectedly (only confirmed via samples, not a live create) | Phase 0 sandbox create test before enabling |
| R2 | **Webhook secret / source IPs** unknown until Kapital provides them | Phase 0 dependency; reconciler covers missing webhooks |
| R3 | **Token expiry races** (300 s, no refresh) under load → 401 storms | cache TTL 240 s, re-auth-once, circuit-breaker |
| R4 | **Webhook dropped** (discarded after retries) → order stuck authorising | reconciler (hourly) + expiry sweep are mandatory |
| R5 | **Webhook/return race** → double application | idempotent transitions + payload_hash dedupe + GET-authoritative |
| R6 | **Idempotency key reuse wrong** → duplicate charge or stuck retry | one persisted `bank_idempotency_key` per order/refund |
| R7 | **Config/Settings drift** (old `config()` still read) → wrong host/secret in prod | SETTINGS_MAPPING verification checklist; grep gate in CI |
| R8 | **Signature scheme mistake** (hex vs Base64, header name) → all webhooks rejected | Base64 + `X-Signature` unit tests against the Java sample |
| R9 | **Amount precision** (minor↔decimal) → wrong charge | central money mapper + tests (10.0 ↔ 1000) |
| R10 | **Refund over-refund / multiple** | net-captured checks preserved + API rejects excess |
| R11 | **Save-card/recurring expectation gap** (product wants auto-renew) | set expectations now: customer-initiated renewals (RECURRING_FLOW) |
| R12 | **PII/PAN in logs** | allowlist + encryption preserved; never log token/secret |
| R13 | **Sandbox vs prod host confusion** (precheckout/preapi/birpay) | hosts pinned per `kapital_mode`; documented in API_REFERENCE §1 |
| R14 | **3-D Secure failures** look like generic failures to users | map `three_ds_verification_failed` to a clear retry message |
| R15 | **Mobile shows success prematurely** (trusting return) | app must read server-reconciled status only (MOBILE_FLOW) |

## Go / no-go gate (before production)

- [ ] Sandbox E2E green (create → 3DS → webhook → succeeded → refund).
- [ ] Webhook signature verified with the real secret; source IPs allowlisted.
- [ ] All payment values served from Settings (config/.env grep clean).
- [ ] Reconciler + expiry verified to settle a deliberately-dropped webhook.
- [ ] Security sign-off (KAPITAL_SECURITY_REVIEW).
- [ ] Rollback: `kapital_enabled=false` instantly disables the new path.
