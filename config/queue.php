<?php

return [

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        // Queue store → Redis logical DB 1 (noeviction). Named queues per Backend Arch §9
        // are configured in config/horizon.php: high, default, device-comm, notifications,
        // payments, reports, privacy.
        'redis' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 300,
            'block_for' => null,
            'after_commit' => true,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => 300,
            'after_commit' => true,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mariadb'),
        'table' => 'job_batches',
    ],

    // Failed jobs retained 30 days; high/payments dead-letters page on-call (Backend Arch §9.4).
    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mariadb'),
        'table' => 'failed_jobs',
    ],

];
