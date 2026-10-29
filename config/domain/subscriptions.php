<?php

/*
| Subscription tunables (Tech Spec §13, App. B; IMPLEMENTATION_PLAN B2 / BR-13, BR-21).
| Commercial rule (business decision 2026-10-29): EVERY subscription (resident = main, each family member =
| additional) costs 15 AZN per year (365 days); it was 12 AZN per 30 days before. The live price and term are
| admin settings (Ayarlar › Abunəliklər); the values below are only the fallback. Prices are in minor units
| (qəpik). They apply to NEW subscriptions and to paid renewals only — existing rows keep their snapshot
| (legacy comps are never re-priced or charged).
| The one-off device SALE price is a separate concept (devices.sale_price_minor) and never feeds a
| subscription amount.
*/
return [

    'term_days' => 365, // fallback — live value: Ayarlar › Abunəliklər › Abunəlik müddəti

    'abandoned_intent_hours' => 24, // B7: unpaid complex subscribe intent → cancelled + roster row revoked
    'grace_days' => 7, // A14: no longer used for renewal math (an ended subscription restarts at payment)

    'reminder_days' => [30, 7, 1], // annual term: D-30 / D-7 / D-1 (a threshold >= a row's own term never fires)

    // Fallback prices in minor units (qəpik). The live subscription prices and term are edited in the admin panel
    // (Ayarlar › Abunəliklər); these apply only while those settings are unset.
    'default_prices_minor' => [
        'device_sale' => 13500, // legacy device_sale order default — NOT a subscription price
        'sub_main' => 1500, // resident — 15 AZN / year
        'sub_additional' => 1500, // family member — same 15 AZN / year as the resident (BR-21)
    ],

    'currency' => 'AZN',

    'auto_renew_enabled' => false, // disabled until Kapital tokenization (R-PAY-14)

];
