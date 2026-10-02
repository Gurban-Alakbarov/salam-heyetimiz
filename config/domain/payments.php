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

    /*
    | Gateway selection (IMPLEMENTATION_PLAN B1 / BR-16). `birpay` (default) keeps the real BirPay gateway.
    | `fake` serves the simulated hosted checkout ("TEST ÖDƏNİŞ") through the SAME order → callback →
    | getOrderStatus → return pipeline, and only when `fake_enabled` is on — plus, in production,
    | `allow_fake_in_production`. A requested-but-not-permitted fake binds an always-unavailable gateway,
    | so a misconfiguration can never fall through to a real bank request. `testing` is always fake.
    */
    'gateway' => env('PAYMENT_GATEWAY', 'birpay'),
    'fake_enabled' => (bool) env('PAYMENT_FAKE_ENABLED', false),
    'allow_fake_in_production' => (bool) env('PAYMENT_ALLOW_FAKE_IN_PRODUCTION', false),
    // Lifetime of the signed fake-checkout links.
    'fake_checkout_ttl_minutes' => 30,

];
