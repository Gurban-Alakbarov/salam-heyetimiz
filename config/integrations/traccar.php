<?php

/*
| Traccar integration (v1.2 transport pivot). Self-hosted Traccar is the primary command + telemetry
| platform for UMKa 310 devices (Wialon IPS/Combine). The backend sends commands via Traccar's REST
| API and ingests telemetry via Traccar's event-forward webhook. 'fake' is the default test/dev binding
| (Backend Arch §13 / R-ARCH-08).
*/
return [

    'driver' => env('TRACCAR_DRIVER', 'fake'), // 'fake' | 'http'

    'base_url' => env('TRACCAR_BASE_URL'),       // e.g. http://traccar.internal:8082
    'api_token' => env('TRACCAR_API_TOKEN'),     // Traccar API bearer token
    'timeout_seconds' => (int) env('TRACCAR_TIMEOUT', 10),

    // Shared secret the event-forward webhook must present (query `token` or header X-Traccar-Token).
    'forward_token' => env('TRACCAR_FORWARD_TOKEN'),

    // Position attribute keys carrying the discrete output state (used for actuation confirmation).
    // Confirm the exact key produced by the UMKa via Traccar in Phase-0 (T3).
    'output_attribute' => env('TRACCAR_OUTPUT_ATTRIBUTE', 'output'),

    // Window (seconds) within which an output-on telemetry confirms a recent dispatched open.
    'actuation_confirm_window_seconds' => (int) env('TRACCAR_ACTUATION_WINDOW', 30),

];
