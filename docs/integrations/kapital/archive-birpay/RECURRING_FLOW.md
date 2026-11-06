# Kapital / BirPay — Recurring Payments

## Finding: NOT available in Checkout Merchant API V1.3

There is **no recurring / subscription / scheduled-charge / merchant-initiated-transaction (MIT) endpoint** in
V1.3. Combined with the absence of card tokenization (see `SAVE_CARD_FLOW.md`), **automatic recurring charges
cannot be implemented** with this API as documented. Every payment requires an interactive, customer-confirmed
hosted-page flow.

## Impact on our subscription model

Our `subscriptions.auto_renew` + `card_tokens` design assumed a stored-card recurring charge. With V1.3 that
path is unavailable. Renewals must be **customer-initiated**:

- **Renewal reminder** (already built: `subscriptions:send-renewal-reminders`) notifies the user before expiry.
- The user taps "renew" → we run the **standard create-payment flow** (`POST /v1/payments`, redirect, confirm).
- On `succeeded` → `OrderPaid` → `renewSubscription` (existing logic, unchanged).
- `auto_renew` becomes a *reminder/convenience* flag, not an unattended charge, until a COF API exists.

## Options (external dependency)

1. **Confirm an MIT/recurring product** with Kapital (separate agreement + API not in this reference). If
   available, design unattended renewals on top of its tokenization + MIT endpoints.
2. **Customer-initiated renewals** (recommended for launch): no stored card, no unattended charge; relies on
   reminders + one-tap checkout.

## Recommendation

- Keep `recurring_enabled` as a **dormant flag**; do not wire unattended charging.
- Implement renewals as customer-initiated payments for the V1.3 integration.
- Track "true recurring" as a **future phase** gated on a Kapital COF/MIT agreement.
