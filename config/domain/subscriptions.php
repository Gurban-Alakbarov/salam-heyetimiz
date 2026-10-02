<?php

/*
| Subscription tunables (Tech Spec §13, App. B; IMPLEMENTATION_PLAN B2 / BR-13, BR-21).
| Commercial rule: EVERY subscription (resident = main, each family member = additional) costs
| 12 AZN per 30 days. Prices are in minor units (qəpik). These apply to NEW subscriptions and to
| paid renewals only — existing rows keep their snapshot (legacy comps are never re-priced or charged).
| The one-off device SALE price is a separate concept (devices.sale_price_minor) and never feeds a
| subscription amount.
*/
return [

    'term_days' => 30,

    'abandoned_intent_hours' => 24, // B7: unpaid complex subscribe intent → cancelled + roster row revoked
    'grace_days' => 7, // "renewed before expiry" extension window after ends_at (R-DOM-08)

    'reminder_days' => [7, 1], // monthly term: D-7 / D-1 only (D-30/D-15 would fire at purchase)

    // Default prices in minor units (qəpik). Source of truth is the settings table.
    'default_prices_minor' => [
        'device_sale' => 13500, // legacy device_sale order default — NOT a subscription price
        'sub_main' => 1200, // resident — 12 AZN / 30 days
        'sub_additional' => 1200, // family member — same 12 AZN / 30 days as the resident (BR-21)
    ],

    'currency' => 'AZN',

    'auto_renew_enabled' => false, // disabled until Kapital tokenization (R-PAY-14)

];
