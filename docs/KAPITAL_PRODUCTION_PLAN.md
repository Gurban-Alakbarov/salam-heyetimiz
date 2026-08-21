# Kapital Bank — Production Plan

**Date:** 2026-06-21
**Companions:** `KAPITAL_INTEGRATION_AUDIT.md` · `KAPITAL_GAP_ANALYSIS.md`. Planning only — no code.
**Objective:** take the Kapital integration from **~25–30% (generic scaffolding)** to a **production-ready**
purchase + save-card + recurring + refund flow against the real Kapital BPC e-commerce API.

> **Strategy:** keep the strong domain layer (orders/payments/refunds, idempotency, encrypted logging,
> dedupe, authoritative `getOrderStatus` cross-check, reconciliation, the `PaymentGateway` seam). **Replace
> the wire layer** inside `KapitalBankClient` and **extend the seam** for card-on-file + recurring.

---

## 0. Prerequisites (external — start now, they gate everything)

| # | Item | Owner |
|---|---|---|
| P1 | Kapital e-commerce **merchant onboarding** → sandbox creds (merchant user/password, terminal, order-type RIDs) | Business |
| P2 | Official **sandbox test cards** + 3DS test OTP (from Notion onboarding) | Business → record in §6 |
| P3 | Confirm from Notion/Kapital: prod host, auth method, **whether a signed S2S callback exists**, refund/reverse verbs, HPP signature | Eng + Bank |
| P4 | Merchant **contract** → production creds + callback/source IP ranges | Business |

## 1. Phase 1 — Confirm the real contract (spike)
- Extract the full Kapital spec from Notion (paste content) + the onboarding pack; lock down: hosts, Basic-Auth
  scheme, `POST /api/order` request/response, order-type RIDs, HPP URL construction, Get Order Info fields
  (incl. `storedId`), `set-src-token`, `exec-tran`, refund/reverse, and the callback model.
- Output: a short `KAPITAL_API_REFERENCE.md` (verbatim endpoints/fields) to implement against.
- **Effort: 3–4 h** (after P1–P3).

## 2. Phase 2 — Rewire the core purchase flow (single payment)
- `config/integrations/kapital.php`: add `username`, `password` (Basic Auth), `terminal`, order-type RIDs
  (`order_type_sms`, `order_type_dms`, `order_type_rec`), prod/sandbox hosts; keep timeouts/retries.
- `KapitalBankClient.registerOrder`: `POST /api/order` `{order:{ typeRid:Order_SMS, amount, currency,
  language, description, hppRedirectUrl }}` (Basic Auth) → parse `{order:{id, password, hppUrl, status}}`;
  return bank order id + **HPP redirect** (from `hppUrl` or `{id,password}`).
- `getOrderStatus`: `GET /api/order/{id}?password=…` → map real status/tran fields → `BankStatus`.
- Confirmation: rely on **redirect-return + `recheckOrder`/`getOrderStatus`** (already built). Treat the
  signed S2S callback as optional pending P3; keep the hardened callback route only if Kapital sends one.
- Mobile/web: `createOrder` already returns the redirect; ensure it returns the **HPP** URL.
- **Effort: 16–24 h** (incl. status mapping + tests).

## 3. Phase 3 — Refund + reverse (real verbs)
- Replace `refund()`/`cancel()` wire calls with Kapital's `exec-tran` **refund/return** and **reverse**;
  keep partial-refund + approved/declined parsing + idempotency + the `refunds` table + `adminRefundOrder`.
- **Effort: 6–10 h.**

## 4. Phase 4 — Save card (card-on-file)
- Extend the `PaymentGateway` seam: `registerCardOnFile(order)` (order with `aut:{purpose:"AddCard"}` +
  `hppCofCapturePurposes:["Cit","Recurring","UnspecifiedMit"]`) and a token read from Get Order Info
  (`storedId`).
- **Migration:** add `card_tokens.stored_id` (Kapital token ref), `expiry_month/year`, `scheme_ref`
  (keep PAN masked only — never PAN/CVV, R-PAY-01).
- Wire the "add card" UX (mobile) → AddCard order → HPP → on return, read `storedId` → persist `card_tokens`.
- **Effort: 16–24 h.**

## 5. Phase 5 — Recurring / auto-renew (MIT)
- Implement `chargeStoredCard(subscription, token)`: `Order_REC` → `set-src-token {token:{storedId},
  order:{initiationEnvKind:"Server"}}` → `exec-tran {tran:{phase:"Single", amount, conditions:{cofUsage:
  "Recurring"}}}` (MIT, no customer).
- Un-gate `AutoRenewService` (remove the P2 `AutoRenewUnavailableException`); wire the
  `subscriptions:send-renewal-reminders`/expiry scheduler to attempt an MIT charge via the stored token,
  with retry/grace + dunning on decline.
- **Effort: 16–24 h.**

## 6. Phase 6 — Sandbox certification (G3 gate)
Test matrix against sandbox (`txpgtst.kapitalbank.az`) with the **official test cards (P2)**:

| Test | Expectation |
|---|---|
| Full purchase (`Order_SMS`, 1.00 AZN) → 3DS → paid → subscription activates | ✅ |
| Declined card / 3DS fail | order failed, no activation |
| Full refund + **partial refund** (`exec-tran`) | refund approved; order → refunded/partially_refunded |
| Reverse (same-day void) | order canceled |
| **Save card** (`AddCard`) → `storedId` persisted | `card_tokens` row created |
| **Recurring** MIT charge with stored token | charge approved, no customer present |
| Callback/redirect storm + duplicate → dedupe | single state transition |
| `getOrderStatus` authoritative (ignore body) | state derived from bank, not callback |
| Amount/currency tampering, replayed callback | rejected |

- **Effort: 8–12 h** (test runs + fixes). This is **Phase-0 gate G3**.

## 7. Phase 7 — Production cutover
- Production creds (P4), prod host, callback/source IP allowlist; secrets in `/root/salam_secrets.env` + `.env`;
  `config:cache`. Small-amount live smoke test (full purchase + refund) with a real card. Enable a **payment
  kill-switch** (a global setting — reuse the `app_settings` foundation from `SMS_FALLBACK_DESIGN.md`).
- **Effort: 4–6 h** (+ bank go-live coordination).

## 8. Effort summary

| Phase | Hours |
|---|---|
| 1 Contract spike | 3–4 |
| 2 Core purchase rewire | 16–24 |
| 3 Refund + reverse | 6–10 |
| 4 Save card | 16–24 |
| 5 Recurring/MIT | 16–24 |
| 6 Sandbox certification (G3) | 8–12 |
| 7 Production cutover | 4–6 |
| **Total** | **~69–104 h** |

**MVP-critical subset (launch payments without recurring/save-card):** Phases 1+2+3+6+7 ≈ **37–56 h** — a
working one-off purchase + refund, certified and live. **Save-card (P4) + recurring (P5)** can be a
fast-follow for subscription auto-renew (≈ 32–48 h more).

## 9. Risks / decisions

- **Callback model:** if Kapital sends no signed S2S webhook, drop the signed-callback dependency and rely on
  redirect-return + `recheckOrder` (already built) + the reconciliation job. Confirm in P3.
- **Auth:** switching from body-HMAC to Basic Auth + order password is the central rewire — low risk, well
  understood, but touches every call.
- **PCI:** stay on **HPP** (hosted 3DS) so PAN/CVV never touch our servers (R-PAY-01); store only `storedId` +
  masked PAN.
- **Idempotency & dedupe:** preserve the existing patterns; recurring MITs must be idempotent per billing period.
- **Test cards:** obtain officially (P2) — none were available from the PDF; do not invent.

**Bottom line:** the foundation is reusable; the work is a focused **wire-layer rewrite + COF/recurring
extension**, ~**37–56 h to launch one-off payments (G3)** and ~**69–104 h** for the full save-card + recurring
suite, plus external bank onboarding lead time.
