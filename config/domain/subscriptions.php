<?php

/*
| Subscription tunables (Tech Spec §13, App. B). Prices live in `settings`
| (qəpik / minor units): prices.device_sale_minor=13500, prices.sub_main_minor=1200,
| prices.sub_additional_minor=600 (placeholder).
*/
return [

    'term_days' => 365,

    'grace_days' => 7, // "renewed before expiry" extension window after ends_at (R-DOM-08)

    'reminder_days' => [30, 15, 7, 1], // D-30/D-15/D-7/D-1 (FR-SUB-04)

    // Default prices in minor units (qəpik). Source of truth is the settings table.
    'default_prices_minor' => [
        'device_sale' => 13500,
        'sub_main' => 1200,
        'sub_additional' => 600,
    ],

    'currency' => 'AZN',

    'auto_renew_enabled' => false, // disabled until Kapital tokenization (R-PAY-14)

];
