<?php

return [

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    'connections' => [

        // Two Reverb nodes behind a sticky LB in prod (R-ARCH-12); state shared via
        // the Redis 'broadcasting' connection (logical DB 3).
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'broadcasting',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
