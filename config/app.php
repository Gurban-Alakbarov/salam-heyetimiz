<?php

return [

    'name' => env('APP_NAME', 'Salam Həyətimiz'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    /*
    | Server runs in UTC; all timestamps are stored UTC (DB Arch §0, Tech Spec §6.6).
    | Human-time crons use Asia/Baku via the scheduler timezone below.
    */
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    'schedule_timezone' => env('APP_SCHEDULE_TIMEZONE', 'Asia/Baku'),

    /*
    | Locale — az is the default & source of truth; ru / en are secondary (R-LOC-01).
    | Fallback is az (not en) per LOCALIZATION_SPECIFICATION §1.
    */
    'locale' => env('APP_LOCALE', 'az'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'az'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'az_AZ'),

    'available_locales' => ['az', 'ru', 'en'],

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
