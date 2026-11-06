<?php

/*
| Kapital Bank e-commerce (hosted 3-D Secure). We never store PAN/CVV (R-PAY-01). Bank credentials live in
| Settings (PaymentSettings), not here.
*/
return [

    'return_url_scheme' => 'salam://payment/return',

    // O3 — an order's own return_url may only send the payer back into the app (salam://…) or to an https page
    // on one of these hosts; anything else is refused (422) so the bank never redirects to an arbitrary site.
    'allowed_return_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'KAPITAL_ALLOWED_RETURN_HOSTS',
        implode(',', array_filter([parse_url((string) env('APP_URL', ''), PHP_URL_HOST), 'sezam.salam.az'])),
    ))))),

];
