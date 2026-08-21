# Kapital Bank — Gap Analysis

**Date:** 2026-06-21
**Companion:** `KAPITAL_INTEGRATION_AUDIT.md` (current state) · `KAPITAL_PRODUCTION_PLAN.md` (the plan).
Audit only — no code.

---

## 1. Completeness by capability

| Capability | Complete | Evidence / gap |
|---|---|---|
| **Payment domain** (orders/items/payments/refunds, idempotency, status machine) | **~80%** | models + services + 30 tests; only wire-layer + COF missing |
| **Logging & dedupe** (encrypted `payment_logs`, `payment_callbacks` payload-hash) | **~90%** | done; reusable |
| **Authoritative status cross-check** (`getOrderStatus` before mutate, R-PAY-04) | **~90%** | done; matches Kapital pull-confirm model |
| **Order registration (wire)** | **~20%** | generic `POST /orders` ≠ real `POST /api/order {order:{typeRid…}}`, Basic Auth, HPP |
| **Hosted Payment Page (HPP) redirect** | **~15%** | code returns a `redirectUrl` field; real flow builds HPP from `{id,password}`/`hppUrl` |
| **Order status / Get Order Info** | **~30%** | method exists; wrong path/fields; no `storedId` read |
| **Refund** | **~40%** | method exists; `/orders/{id}/refund` ≠ `exec-tran` refund |
| **Cancel / reverse** | **~30%** | method exists; verb/path unconfirmed |
| **Callback verification** | **~50%** | hardening solid; model likely redirect+pull, not signed S2S webhook |
| **HMAC / auth** | **~30%** | `X-Merchant-Signature` body-HMAC ≠ Basic Auth + order password |
| **Save card (COF / AddCard / storedId)** | **0%** | no interface method, no `AddCard`, no `hppCofCapturePurposes`, **no token column in `card_tokens`** |
| **Recurring / MIT** (`set-src-token` + `exec-tran`) | **~10%** | subscription auto-renew scaffolded but P2-gated; no Kapital recurring calls |
| **Sandbox/prod config** | **~25%** | base_url/merchant_id/hmac only; missing Basic-Auth creds, order-type RIDs |
| **Test coverage vs real API** | **~30%** | tests cover the *generic* contract + callback hardening, not real Kapital shapes |
| **OVERALL production-ready Kapital** | **~25–30%** | strong scaffolding, generic/placeholder wire layer |

## 2. Missing endpoints / operations

| # | Needed (real Kapital) | Status |
|---|---|---|
| 1 | `POST /api/order` (create with `typeRid` `Order_SMS`/`Order_DMS`/`Order_REC`, `aut`, `hppCofCapturePurposes`) | ❌ not implemented (generic `/orders`) |
| 2 | HPP redirect construction from `{id, password}` / `hppUrl` | ❌ |
| 3 | `GET /api/order/{id}?password=…` (full info incl. `storedId`, transactions) | ⚠️ wrong path/fields |
| 4 | `POST /api/order/{id}/set-src-token?password=…` (link `storedId`) | ❌ |
| 5 | `POST /api/order/{id}/exec-tran` (charge: `phase=Single`, `cofUsage`) | ❌ |
| 6 | `exec-tran` **refund/return** | ❌ (have generic `/refund`) |
| 7 | **reverse** (same-day void) | ⚠️ generic `/cancel` |
| 8 | Save-card token read + persist (`storedId` → `card_tokens`) | ❌ (no column) |

## 3. Save-card support

- **Status: 0% implemented.**
- Real flow (PDF): create `Order_SMS` with `aut:{purpose:"AddCard"}` + `hppCofCapturePurposes:["Cit",
  "Recurring","UnspecifiedMit"]` → customer completes HPP → a **`storedId`** token is issued, read via Get
  Order Info → persist as a reusable card-on-file token.
- Gaps: no interface method (`saveCard`/`registerCardOnFile`), no `AddCard` order shaping, and **`card_tokens`
  lacks a `stored_id`/`gateway_token` + `expiry`/`scheme-ref` column** to hold the Kapital token. PAN/CVV are
  correctly never stored (R-PAY-01) — only the token + masked PAN should be kept.

## 4. Recurring payments support

- **Status: ~10% (scaffolding only).**
- `AutoRenewService` is **P2-gated** — enabling auto-renew throws `AutoRenewUnavailableException` "no
  tokenization". `subscriptions.card_token_id` + `card_tokens` exist but are not fed by Kapital.
- Real flow (PDF): `Order_REC` → `set-src-token {token:{storedId}, order:{initiationEnvKind:"Server"}}` →
  `exec-tran {tran:{phase:"Single", amount, conditions:{cofUsage:"Recurring"}}}` (a Merchant-Initiated
  Transaction; no customer present). None of this exists.

## 5. Refund support

- **Status: ~40% (method present, wrong wire).** `refund(bankOrderId, amountMinor, idempotencyKey)` exists in
  the interface, the `RefundService`, the admin `adminRefundOrder` endpoint, and the `refunds` table — but it
  posts to a generic `/orders/{id}/refund`, not Kapital's `exec-tran` refund/return. Partial refunds and the
  approved/declined parsing are modelled; only the wire call + status mapping need correcting.

## 6. Callback verification

- **Status: ~50% (hardened, but mis-modelled).** Current: signed S2S webhook `{orderId,status}` verified by
  raw-body HMAC + IP allowlist + dedupe + authoritative `getOrderStatus` cross-check.
- Reality: Kapital confirms via **browser redirect to `hppRedirectUrl`** + the merchant **pulling Get Order
  Info** (authoritative). The app already supports this (`recheckOrder` + `getOrderStatus`). The signed S2S
  callback may not be sent by Kapital — **must confirm**. If it isn't, the callback route becomes optional
  and the redirect-return + recheck path becomes the primary confirmation.

## 7. HMAC requirements

- Current: `KapitalSignature` HMAC-SHA256 over raw bytes for an `X-Merchant-Signature` header **and** callback
  verification.
- Real: Kapital authenticates the merchant API with **HTTP Basic** (user + password); the **order `password`**
  secures HPP + Get Order Info; HPP URLs may carry a signature/hash. **The body-HMAC header is likely not
  used.** Action: confirm in Notion; repurpose or retire `KapitalSignature` accordingly. (Constant-time
  raw-byte comparison logic is reusable if any signature exists.)

## 8. Sandbox vs production differences

| Aspect | Sandbox | Production |
|---|---|---|
| Host | `txpgtst.kapitalbank.az` (PDF) | `e-commerce.kapitalbank.az` / `tpg.kapitalbank.az` (confirm) |
| Credentials | test merchant user/password + terminal | issued after onboarding/contract |
| Cards | Kapital **test cards** (in Notion onboarding) | real cards |
| 3-D Secure | test ACS (fixed OTP) | live 3DS |
| Amounts | `1.0 AZN` test orders (PDF) | real |
| IP allowlist | test ranges | production callback/source ranges |
| Settlement | none | real funds/settlement reports |

## 9. Test cards

The "Save Card Process" PDF contains **order JSON only — no card numbers**. The Notion page is JS-rendered
and could not be auto-extracted, so the **specific test PANs / expiry / 3DS OTP were not obtained here.**
They must be taken from the Kapital sandbox onboarding (Notion "test cards" section). **Do not invent card
numbers.** Action: capture the official sandbox test cards before G3 testing and record them in
`KAPITAL_PRODUCTION_PLAN.md` §test-matrix.

## 10. Summary

Reusable & strong: domain model, idempotency, encrypted logging, dedupe, authoritative cross-check,
reconciliation, the `PaymentGateway` seam. Must build/fix: the entire **real Kapital wire layer** (auth,
`/api/order`, order types, HPP), **save-card (COF)**, **recurring (`set-src-token`/`exec-tran`)**, refund/reverse
wire format, the `card_tokens.stored_id` column, and confirmation of the callback model. **~25–30% complete.**
