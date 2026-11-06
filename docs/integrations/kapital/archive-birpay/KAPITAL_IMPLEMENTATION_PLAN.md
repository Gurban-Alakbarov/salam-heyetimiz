# Kapital / BirPay — Implementation Plan, Effort & Dependencies

> Plan only. No code in this phase. Effort assumes 1 senior dev + Opus pairing; hours are engineering hours.

## Phases

### Phase 0 — Pre-flight (blocked on Kapital) — *not engineering*
Resolve the external dependencies below (hosts, webhook registration + secret + IPs, prod creds, card/REDIRECT
confirmation). Do **one** sandbox create→redirect→3DS→webhook test to lock the unknowns.

### Phase 1 — Auth + HTTP client core — ~22 h
- `BirPayTokenService`: client_credentials token fetch, server-side cache (TTL < 300 s), re-auth on 401. (6 h)
- Rewrite `KapitalBankClient` → `BirPayClient`: `createPayment`, `getPayment`, `cancelPayment`,
  `createRefund`, `getRefund`; Bearer auth; `X-Idempotency-Key`; error-envelope mapping; 5xx-retry + re-auth. (16 h)

### Phase 2 — Domain wiring — ~18 h
- Rewrite `BankStatus` enum + status→`OrderStatus` mapping (pending/succeeded/canceled/waiting_for_capture). (4 h)
- Request/response DTOs (create-payment, payment object, refund object). (4 h)
- Wire `OrderService` create → `createPayment`, store `id`/`confirmUrl`; idempotency-key persistence. (4 h)
- Refund execute → `createRefund`. (3 h)
- `PaymentLogger` allowlist update to BirPay fields. (3 h)

### Phase 3 — Webhook + callback — ~16 h
- Rewrite `KapitalSignature` → Base64 HMAC, verify-only; `VerifyKapitalSignature` → `X-Signature`. (4 h)
- Webhook controller: parse `{event, payload.id}`, dedupe, enqueue verify-via-GET job, return 200. (6 h)
- Browser `returnUrl` endpoint: reconcile via GET, render result / app deep-link. (4 h)
- Reconciliation job tweak for `pending` past `expiresAt`. (2 h)

### Phase 4 — Settings + config — ~5 h
- Add `kapital_scope`; collapse success/fail/cancel → `return_url`; retire `kapital_checkout_url`. (2 h)
- Wire `BirPayClient` to read base/creds/scope/secret from Settings (live) with config fallback. (3 h)

### Phase 5 — Tests — ~18 h
- `FakeBirPayGateway` test double. (4 h)
- Feature tests: create→pending→confirmUrl; webhook succeeded/canceled (signature ✓/✗); verify-via-GET
  authoritative; refund full/partial; idempotency replay; 401 re-auth; 5xx retry; error envelope mapping. (14 h)

### Phase 6 — Sandbox E2E + hardening + docs — ~14 h
- End-to-end sandbox run with the test card; confirm 3DS + webhook secret + IPs. (8 h)
- OpenAPI + docs updates; security sign-off. (4 h)
- Production config + go-live checklist. (2 h)

## Effort summary

| Phase | Hours |
|---|---|
| 1 Auth + client | 22 |
| 2 Domain wiring | 18 |
| 3 Webhook + callback | 16 |
| 4 Settings | 5 |
| 5 Tests | 18 |
| 6 Sandbox E2E + docs | 14 |
| **Subtotal** | **93** |
| Buffer / review / integration (~20%) | ~18 |
| **Total** | **≈ 95–115 h** (≈ 2.5–3 weeks, 1 dev) |

*Excludes any future save-card/recurring work (needs a separate Kapital agreement — not scoped here).*

## External dependencies (must close before/early in coding)

1. **Canonical hosts** confirmed (preapi/api.birpay.az vs the precheckout host on the credential sheet).
2. **Webhook registration**: our URL subscribed; **`X-Signature` shared secret** obtained; **source IPs** for allowlist.
3. **Card + REDIRECT** hosted 3-D Secure behaviour + exact `confirmUrl` shape confirmed in sandbox.
4. Whether **refund webhook events** are delivered (else poll-only).
5. Whether **merchant/terminal** are required in the e-commerce payment body (vs `posDetail`/token only).
6. **Production credentials** + live MID/TID + MCC + merchant display name.
7. **Idempotency replay window / TTL** policy from Kapital.
8. (Future) **COF/MIT/tokenization** agreement if save-card/recurring is ever required.

## Risks

- REDIRECT/`confirmUrl` for cards is **[INFER]** — biggest unknown; close it with one sandbox test before
  building UX.
- Token lifetime 300 s, no refresh → must cache + auto-renew cleanly under load.
- Webhook may be dropped (discarded after retries) → reconciliation job is mandatory, not optional.
- Save-card/recurring expectations vs API reality — set product expectations now (customer-initiated renewals).
