<?php

/*
| Universal links / Android App Links (IMPLEMENTATION_PLAN B6) — served by Laravel at
| /.well-known/assetlinks.json and /.well-known/apple-app-site-association. A platform answers 404
| until its identity is configured (the signing-certificate fingerprints and the Apple Team ID are
| deploy-time values and never committed). Store links feed the /invite/{token} web landing.
*/
return [
    'android' => [
        'package' => env('APP_LINKS_ANDROID_PACKAGE', 'com.salamheyetimiz.salam_mobile'),
        // comma-separated SHA-256 signing-cert fingerprints (AA:BB:…)
        'sha256_cert_fingerprints' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_LINKS_ANDROID_SHA256', ''))))),
    ],
    'ios' => [
        // "<TEAMID>.<bundle id>", e.g. ABCDE12345.com.salamheyetimiz.salamMobile
        'app_id' => env('APP_LINKS_IOS_APP_ID'),
    ],
    'paths' => ['/invite/*'],
    'store' => [
        'android' => env('APP_STORE_URL_ANDROID'),
        'ios' => env('APP_STORE_URL_IOS'),
    ],
];
