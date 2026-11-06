# Kapital e-commerce API: what we use

This is the API the bank confirmed for production (2026-11-06). It replaces BirPay V1.3 as our payment provider.
Test results are in `SANDBOX_RESULTS.md`. The bank's full document is archived in `raw/notion_2026-11-06.md`. Facts below marked **[verified]** were seen on
the bank's test host; the rest come from the document.

## Hosts and login

| Mode | Base URL |
|---|---|
| `sandbox` | `https://txpgtst.kapitalbank.az/api` |
| `production` | `https://e-commerce.kapitalbank.az/api` |

- Auth: HTTP Basic, on every call. **[verified]**
- Test login: the shared one in the bank's document. The bank issues our own production login after the tests.
- No IP whitelist is needed (bank). Our production server reaches both hosts. **[verified]**
- There is no webhook (bank). The only signal is the browser redirect.

Settings: `kapital_ecom_username`, `kapital_ecom_password` and `kapital_mode` in Ayarlar › Payments. The gateway is
selected with `PAYMENT_GATEWAY=kapital`.

## Calls

| Purpose | Call |
|---|---|
| Create order | `POST /order` with `{order: {typeRid: "Order_SMS", amount: "15.00", currency, language, title, description, hppRedirectUrl}}` |
| Read order | `GET /order/{id}?tranDetailLevel=2&tokenDetailLevel=2&orderDetailLevel=2` |
| Refund | `POST /order/{id}/exec-tran` with `{tran: {phase: "Single", amount: "5.00", type: "Refund"}}` |

- A refund's answer has `pmoResultCode`, `approvalCode` and `match.ridByPmo`, but no RRN. The RRN is on the refund's
  entry in the order's `trans[]`, so we read the order after a refund. **[verified]**
- Each transaction has both `rrn` (12 digits, what the bank's support asks for) and `ridByPmo`. **[verified]**
- Create returns `{order: {id, hppUrl, password, status, secret, ...}}`. **[verified]**
- The hosted page is `{hppUrl}?id={id}&password={password}`. **[verified]** `hppUrl` already ends in `/flex`, so the
  sample `{{hppUrl}}/flex?...` in the bank's document is wrong.
- `exec-tran` does not need the order password in the URL. **[verified]** A refund on an unpaid order reaches the
  order logic and answers `InvalidOrderState`.
- The hosted page offers AZ, EN and RU and has a "Cancel order" button. **[verified]**
- The API has no idempotency key and no call that cancels an unpaid order.

## Return redirect

The bank sends the browser to `hppRedirectUrl?ID={id}&STATUS={status}`. **[verified]** `Declined` and `Cancelled`
were seen. The document says `STATUS` can be temporary, so `PaymentReturnController` only uses `ID` to find the order
and reads the status from the bank.

## Status mapping

| Bank status | `BankStatus` | Seen on the test host |
|---|---|---|
| `FullyPaid` | Approved | yes |
| `Preparing` | Pending | yes |
| `PartPaid` (after a partial refund, not in the bank's document) | Approved if money remains, else Pending | yes |
| `Authorized`, `Funded`, anything unknown | Pending | no |
| `Declined`, `Rejected` | Declined | `Declined` yes |
| `Cancelled`, `Refused`, `Expired` | Canceled | `Cancelled`, `Expired` yes |
| `Refunded` | Refunded | yes |
| `Voided` | Refunded | no |
| `Closed` | Approved if money remains, else Refunded | no |

An unpaid order expires at the bank about 10 minutes after it is created **[verified]**, sooner than our order TTL.

## Errors

`{errorCode, errorDescription, errorDetails?}`. **[verified]**

| Case | HTTP | `errorCode` / description |
|---|---|---|
| Unknown order | 404 | `ServiceError` / `no order found` |
| Wrong login | 500 | `ServiceError` / `ApeError.QueryAppError.InvalidLogin` |
| Refund on an unpaid order | 400 | `InvalidOrderState` / `Order is completed` |
| No login at all | 400 | |

A declined card is not an API error: the order is `Declined`, and the transaction in `trans[]` carries
`pmoResultCode`, `declineReason` and `pmoDeclineDescription`. **[verified]**

Settings' connection check reads order 999999999: a 404 means the login works and nothing is created.

## How we handle the gaps

- **No webhook:** `payments:reconcile-orders` runs every 5 minutes and `kapital_authorising_recheck_minutes` defaults
  to 3. A customer who pays and closes the page is found this way.
- **No idempotency key:** only GET is retried. A refund first compares the bank's `clearedRefundAmount` with the
  refunds we recorded. If the bank already refunded that amount, the lost refund is recorded and nothing is sent.
- **Secrets:** the response's `password` and `secret`, and the buyer's `consumerDevice` data, never reach
  `payment_logs` or the stored payment row. `KapitalEcomClient` logs a named field list only.
- **Bank-side cancel:** none. `cancel()` returns false; the unpaid order expires at the bank.

## Not used yet

The API also has card saving and recurring payments (`Order_REC`, `set-src-token`), preauthorization (`Order_DMS`),
same-day reversal (`voidKind`), instalments and transfers to a card (`OCT`).
