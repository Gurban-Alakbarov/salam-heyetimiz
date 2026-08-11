<?php

/*
| Payment-domain tunables (Tech Spec §14, App. B). Provider hosted-page details
| live in config/integrations/kapital.php.
*/
return [

    'provider' => 'kapital',

    'currency' => 'AZN',

    // Pending/authorising order TTL → failed if no callback (Tech Spec App. B).
    'callback_timeout_minutes' => 30,

    // Hourly reconciler resolves authorising orders older than this (R-PAY-13).
    'authorising_recheck_after_minutes' => 30,

    // Refund pre-checks (R-PAY-09).
    'refund_window_days' => 365,

    // At most one authorising order per subscription at a time (R-PAY-11).
    'one_authorising_order_per_subscription' => true,

    // getOrderStatus cross-check is non-negotiable (R-PAY-04).
    'always_verify_with_get_order_status' => true,

];
