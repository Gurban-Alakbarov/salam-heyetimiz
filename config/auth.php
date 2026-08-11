<?php

/*
| Auth scaffolding. The two disjoint JWT guards (mobile / admin, R-API-03) are
| registered in App\Providers\AuthServiceProvider in the Auth-module increment
| (lcobucci/jwt, RS256, kid — R-SEC-02/05/08). The defaults below cover the admin
| Blade session and the eloquent user provider only.
*/
return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'admins'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],

        // Disjoint RS256 JWT guards (R-API-03). The drivers are registered as
        // Auth::viaRequest in Auth\ModuleServiceProvider, so actingAs() still works in tests.
        // Guard names are dot-free so config('auth.guards.<name>') resolves correctly.
        'user' => [
            'driver' => 'salam-jwt-user',
            'provider' => 'users',
        ],
        'admin' => [
            'driver' => 'salam-jwt-admin',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Domain\Users\Models\User::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Domain\Admin\Models\AdminUser::class,
        ],
    ],

    'passwords' => [
        'admins' => [
            'provider' => 'admins',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
