<?php

/*
| SMS provider (OTP, invites, expiry reminders, device whitelist programming).
| The concrete AZ provider is selected in Phase 0; 'fake' is the default test/dev
| binding (Backend Arch §13). Inbound replies are HMAC-authenticated (R-API-08).
*/
return [

    'provider' => env('SMS_PROVIDER', 'fake'),

    'base_url' => env('SMS_BASE_URL'),
    'api_key' => env('SMS_API_KEY'),
    'sender_id' => env('SMS_SENDER_ID', 'SalamHayet'),

    'inbound_hmac_secret' => env('SMS_INBOUND_HMAC_SECRET'),

    'timeout_seconds' => 10,

    // Fallback international provider (Tech Spec §12.8) — configured later.
    'fallback_provider' => env('SMS_FALLBACK_PROVIDER'),

];
