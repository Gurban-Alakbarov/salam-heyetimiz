<?php

use Illuminate\Support\Str;

/*
| Horizon — named queues per BACKEND_ARCHITECTURE §9.1. Redis queue store is the
| 'queue' connection (logical DB 1). Worker counts below mirror the documented
| "initial" sizing for a modest single-dev scale; revisit at end of Phase 1.
*/
return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'queue',

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'salam'), '_').'_horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:high' => 30,
        'redis:payments' => 60,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 43200, // 30 days (Backend Arch §9.4)
        'monitored' => 10080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,

    'memory_limit' => 256,

    'defaults' => [
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high'],
            'balance' => 'auto',
            'maxProcesses' => 8,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'maxProcesses' => 4,
            'tries' => 3,
            'timeout' => 120,
        ],
        'supervisor-device-comm' => [
            'connection' => 'redis',
            'queue' => ['device-comm'],
            'balance' => 'auto',
            'maxProcesses' => 8, // CLIP=4 / SMS=8 split enforced at job-middleware level
            'tries' => 5,
            'timeout' => 120,
        ],
        'supervisor-notifications' => [
            'connection' => 'redis',
            'queue' => ['notifications'],
            'balance' => 'auto',
            'maxProcesses' => 6,
            'tries' => 5,
            'timeout' => 120,
        ],
        'supervisor-payments' => [
            'connection' => 'redis',
            'queue' => ['payments'],
            'balance' => 'auto',
            'maxProcesses' => 3,
            'tries' => 5,
            'timeout' => 120,
        ],
        'supervisor-reports' => [
            'connection' => 'redis',
            'queue' => ['reports'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'tries' => 1,
            'timeout' => 3600,
        ],
        'supervisor-privacy' => [
            'connection' => 'redis',
            'queue' => ['privacy'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'tries' => 2,
            'timeout' => 600,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-high' => ['maxProcesses' => 8],
            'supervisor-default' => ['maxProcesses' => 4],
            'supervisor-device-comm' => ['maxProcesses' => 8],
            'supervisor-notifications' => ['maxProcesses' => 6],
            'supervisor-payments' => ['maxProcesses' => 3],
            'supervisor-reports' => ['maxProcesses' => 2],
            'supervisor-privacy' => ['maxProcesses' => 2],
        ],
        'local' => [
            'supervisor-high' => ['maxProcesses' => 3],
            'supervisor-default' => ['maxProcesses' => 2],
            'supervisor-device-comm' => ['maxProcesses' => 2],
            'supervisor-notifications' => ['maxProcesses' => 2],
            'supervisor-payments' => ['maxProcesses' => 1],
            'supervisor-reports' => ['maxProcesses' => 1],
            'supervisor-privacy' => ['maxProcesses' => 1],
        ],
    ],

];
