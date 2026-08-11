<?php

return [
    // Master switch. When false, every /api/docs* and /api/openapi* route returns 404.
    'enabled' => env('API_DOCS_ENABLED', true),

    // When true, the docs (Swagger UI, ReDoc, and the raw spec) require HTTP Basic Auth.
    // Defaults to ON in production so anonymous users cannot view the API surface (R-DOCS-01).
    'protect' => env('API_DOCS_PROTECT', env('APP_ENV') === 'production'),

    // Basic-auth credentials used when 'protect' is true. If the password is empty while
    // protection is on, access is denied to everyone (fail-closed).
    'username' => env('API_DOCS_USER', 'admin'),
    'password' => env('API_DOCS_PASSWORD'),

    'title' => 'Salam Həyətimiz API',

    // Versioned spec files (relative to the project root). The default version is served at
    // /api/openapi.json; explicit versions at /api/{version}/openapi.json.
    'default_version' => 'v1',
    'specs' => [
        'v1' => 'docs/openapi/openapi.json',
        // 'v2' => 'docs/openapi/openapi.v2.json',  // add when v2 lands
    ],

    // CDN asset versions for the rendered UIs.
    'swagger_ui_version' => '5.17.14',
    'redoc_version' => '2.1.5',
];
