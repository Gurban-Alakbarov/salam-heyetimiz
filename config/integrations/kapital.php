<?php

/*
| Kapital Bank e-Commerce (hosted 3-D Secure). We never store PAN/CVV (R-PAY-01).
| Callback hardening (R-PAY-03/04/06/07): raw-body HMAC + IP allowlist + mandatory
| getOrderStatus cross-check + payload_hash dedupe.
*/
return [

    'base_url' => env('KAPITAL_BASE_URL'),
    'merchant_id' => env('KAPITAL_MERCHANT_ID'),

    // HMAC secret for callback signature verification (rotated per R-SEC-10).
    'hmac_secret' => env('KAPITAL_HMAC_SECRET'),

    // Comma-separated source IPs allowed to hit /v1/payments/callback.
    'ip_allowlist' => array_filter(array_map('trim', explode(',', (string) env('KAPITAL_IP_ALLOWLIST', '')))),

    'timeout_seconds' => 15,
    'connect_timeout_seconds' => 5,
    'retries' => 2,

    'return_url_scheme' => 'salam://payment/return',

    // O3 — an order's own return_url may only send the payer back into the app (salam://…) or to an https page
    // on one of these hosts; anything else is refused (422) so the bank never redirects to an arbitrary site.
    'allowed_return_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'KAPITAL_ALLOWED_RETURN_HOSTS',
        implode(',', array_filter([parse_url((string) env('APP_URL', ''), PHP_URL_HOST), 'sezam.salam.az'])),
    ))))),

];
