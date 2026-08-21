# Kapital / BirPay — Save Card / Tokenization

## Finding: NOT available in Checkout Merchant API V1.3

The V1.3 API exposes **only** payments and refunds. There is **no** endpoint for:
- saving / tokenizing a card,
- listing stored cards,
- charging a stored card / credential-on-file (COF),
- "save card for later" during checkout.

The only card-related concepts present are:
- `paymentMethodData.type = BANK_CARD` (pay with a card on the hosted page),
- `capture: true|false` → two-phase **authorise then capture** (`waiting_for_capture`) — this is *deferred
  capture of a single payment*, **not** card storage.
- `metadata.instalmentTerms` → instalment ("taksit") options shown to the customer — a bank feature, not COF.

## Implication

"Save Card" as a product feature **cannot be built on V1.3 as documented**. The current backend's
`card_tokens` table + `save_card_enabled` flag have **no API to populate them** with this version.

## Options (require Kapital confirmation — external dependency)

1. **Confirm a COF/tokenization extension** exists for the BirPay/Kapital acquiring product (a separate
   agreement / API surface not in this Notion reference). If yes, obtain that spec and design then.
2. **No save-card** for launch: every purchase/renewal is an interactive hosted-page payment. Subscriptions
   renew by sending the customer a fresh payment link / in-app checkout (see `RECURRING_FLOW.md`).

## Recommendation

- Keep `card_tokens` table + `save_card_enabled` flag as **dormant infrastructure** (do not wire them).
- Mark Save Card **out of scope** for the V1.3 integration; revisit only if Kapital provides a tokenization API.
- Never store PAN/CVV (R-PAY-01) — the hosted-page model already guarantees card data never reaches us.
