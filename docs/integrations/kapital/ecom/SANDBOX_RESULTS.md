# Kapital e-commerce sandbox results

Run on 2026-11-06 with the real `OrderService`, `KapitalEcomGateway`, return page, `RefundService` and reconciler,
against `txpgtst.kapitalbank.az` with the shared test login. Our side ran locally on a throwaway database.

| Scenario | Bank order | Bank status | Our order | Subscription | Result |
|---|---|---|---|---|---|
| Successful payment, Visa, 15 AZN | 294119 | FullyPaid | paid | active | Pass |
| Partial refund, 5 AZN | 294119 | PartPaid | partially refunded | active, term shortened | Pass |
| Refund of the remaining 10 AZN | 294119 | Refunded | refunded | refunded | Pass |
| Paid, customer never returns, Mastercard, 15 AZN | 294120 | FullyPaid | paid by the reconciler | active | Pass |
| Refund the bank made while its answer was lost | 294120 | Refunded | refunded, nothing sent twice | refunded | Pass |
| Declined card, 15 AZN and 1 AZN | 294079, 294082 | Declined | failed | stays pending | Pass |
| "Cancel order" on the hosted page | 294080 | Cancelled | cancelled | stays pending | Pass |
| Unpaid order left alone | 294074 | Expired after 10 min | | | Pass |
| Reconciler on an unpaid order | 294082 | Preparing | stays authorising | stays pending | Pass |
| Refund on an unpaid order | 294079 | | | | Pass: the bank refuses with `InvalidOrderState` |

## RRNs

| Bank order | Operation | Amount | RRN |
|---|---|---|---|
| 294119 | Purchase | 15 AZN | 338944575996 |
| 294119 | Refund | 5 AZN | 352774575998 |
| 294119 | Refund | 10 AZN | 508764576001 |
| 294120 | Purchase | 15 AZN | 652674576032 |
| 294120 | Refund | 15 AZN | 711814576034 |
| 294079 | Purchase, declined | 15 AZN | 954734574484 |
| 294082 | Purchase, declined | 1 AZN | 134464574499 |

## Test cards

The bank's document lists expiry dates that no longer work. The bank sent current ones on 2026-11-06 (cards ending
1778 and 3468); with the old dates the payment is declined with code 52 or refused by the hosted page.

## Not tested

- The bank's hosted page inside the mobile app's WebView. The page and its redirects were tested in a browser.
- Production: this needs our own login from the bank.
