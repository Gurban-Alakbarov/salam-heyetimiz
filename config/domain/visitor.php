<?php

/*
| Visitor links (temporary shareable barrier access). The link host is the app's public web origin
| (Laravel serves /v/{token} — see routes/web.php); it is deliberately NOT the api. subdomain.
*/
return [
    // Base origin for the shareable URL: https://salamheyetimiz.com  → /v/{token}
    'base_url' => rtrim((string) env('VISITOR_LINK_BASE_URL', (string) config('app.url')), '/'),
    'path' => 'v',

    // A one_time link that is never used still dies after this, so it cannot linger forever. Kept in step
    // with the 12h ceiling so no link outlives the maximum window by default.
    'one_time_ttl_minutes' => (int) env('VISITOR_ONE_TIME_TTL_MINUTES', 720), // 12h

    // Guard rails for time_limited links (minutes). Default is short (30 min); the ceiling is 12h and can
    // only be raised deliberately via VISITOR_MAX_DURATION_MINUTES ("explicitly overridden").
    'min_duration_minutes' => 5,
    'max_duration_minutes' => (int) env('VISITOR_MAX_DURATION_MINUTES', 720), // 12h
    'default_duration_minutes' => (int) env('VISITOR_DEFAULT_DURATION_MINUTES', 30),

    // Anti-abuse: how many *active* (live) links may exist at once. Counted on creation; <= 0 disables the
    // check. per_creator bounds one resident; per_device bounds a single barrier across all creators.
    'max_active_per_creator' => (int) env('VISITOR_MAX_ACTIVE_PER_CREATOR', 5),
    'max_active_per_device' => (int) env('VISITOR_MAX_ACTIVE_PER_DEVICE', 20),
];
