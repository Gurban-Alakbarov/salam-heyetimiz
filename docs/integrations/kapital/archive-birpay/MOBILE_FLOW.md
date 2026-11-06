# Kapital / BirPay — Mobile (Flutter) Flow

The app never touches card data or the BirPay API directly. It talks only to **our** `/v1/orders` API (which is
**unchanged** by this integration) and opens the bank's hosted page in a browser/WebView.

## Endpoints the app uses (existing, unchanged)

| Action | Call |
|---|---|
| Create order | `POST /v1/orders` + `Idempotency-Key` header → `{ id, reference, status, bank_redirect_url, expires_at, items }` |
| Get order | `GET /v1/orders/{id}` → order + `payments[]` + `refunds[]` + `timeline[]` |
| Recheck (poll) | `POST /v1/orders/{id}/recheck` → refreshed order (server re-reads gateway) |
| Subscriptions | `GET /v1/subscriptions`, `POST /v1/subscriptions/{id}/renew` (creates an order) |

## Flow

```
1. CREATE
   app → POST /v1/orders { purpose, items[], return_url, Idempotency-Key }
   ← 201 { id, status:"authorising", bank_redirect_url, expires_at }

2. REDIRECT
   app opens `bank_redirect_url` (confirmUrl) in an in-app browser / WebView
   (flutter_custom_tabs / webview_flutter). PAN/3-D Secure happen on the bank page.

3. RETURN URL
   When the customer finishes, the bank redirects to our `return_url`.
   Use a single HTTPS universal/app link, e.g. https://api.salamheyetimiz.com/v1/payments/return,
   which 302-deep-links back into the app (salam://payment/return). Do NOT rely on the raw custom scheme
   as the bank returnUrl unless Kapital confirms it.

4. RECONCILE (authoritative)
   On return (or WebView close), app → POST /v1/orders/{id}/recheck → reads server-reconciled status:
     - paid      → SUCCESS screen (subscription/device now active)
     - failed    → FAILURE screen (+ reason)
     - cancelled → CANCELLED screen
     - expired   → EXPIRED screen (offer retry = new order)
     - authorising/pending → keep polling (step 5)

5. POLLING (fallback when return is missed)
   If status is still authorising after return/close, poll GET /v1/orders/{id} (or recheck) with backoff
   (e.g. 2s, 3s, 5s, 8s … cap ~60s, stop at expires_at). Stop on any terminal status.

6. WEBHOOK SYNCHRONISATION
   The app does NOT receive webhooks. The bank's webhook updates the order server-side; the app simply sees the
   updated status via recheck/get. Webhook + return can race — the server reconciles idempotently, so the app
   always reads a consistent status.
```

## Success / Failure / Cancel screens

- **Success**: order `paid` → show receipt (reference, amount, paid_at); navigate to the activated subscription/device.
- **Failure**: order `failed` → show `failed_reason` (mapped to a friendly message: 3-D Secure failed,
  insufficient funds, declined…), offer **Try again** (creates a new order with a new Idempotency-Key).
- **Cancel**: order `cancelled` → user abandoned; offer retry.
- **Expired**: order `expired` (no action before `expires_at`) → offer retry.

## Offline behaviour

- If the device is offline at **create** time → show "no connection"; do not create a local order.
- If offline at **return/poll** time → the order already exists server-side; cache its `id` locally and
  reconcile when connectivity returns (poll on resume). Never assume success without a server `recheck`.
- The hosted page itself requires connectivity (it's a remote page); there is no offline payment.

## Retry

- **Idempotency**: reuse the SAME `Idempotency-Key` only to retry the *same* create call after a network error
  (avoids a duplicate order). A *new* purchase attempt uses a NEW key.
- **In-flight guard**: the backend allows ≤1 authorising order per subscription, so a double-tap can't create
  two charges — the app receives the existing in-flight order.
- **Resume**: on app relaunch with a pending order id cached → recheck; route to the right screen.

## What the app must implement (checklist)

- [ ] Generate + send `Idempotency-Key` (UUID) per purchase.
- [ ] Open `bank_redirect_url` in an in-app browser; detect `return_url` to close it.
- [ ] Recheck on return + poll with backoff until terminal or `expires_at`.
- [ ] Map order status + `failed_reason` to localized screens (az/ru/en).
- [ ] Persist the in-flight order id; reconcile on resume.
- [ ] Never display "paid" without a server-confirmed `paid` status.
