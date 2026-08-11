<?php

return [

    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        // Application cache → Redis logical DB 0 (allkeys-lru). R-ARCH-10.
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            // Locks (cooldowns, whitelist drain, scheduler guards) → Redis logical DB 2.
            'lock_connection' => 'locks',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'salam_cache_'),

];
