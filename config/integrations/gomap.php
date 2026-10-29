<?php

/*
| GoMap.az (2026-10-29) — replaces OpenStreetMap: map tiles, address search and reverse geocoding (pin → address with
| house number). The GUID is issued for THIS project only; it lives in .env (GOMAP_GUID), never in git. The API is
| called server-side (key hidden, cached, rate-limited); tiles are fetched by the clients from the URL the API hands
| out (GoMap's own web API exposes the GUID in the tile URL the same way).
*/
return [
    'guid' => env('GOMAP_GUID'),
    'api_url' => env('GOMAP_API_URL', 'https://api.gomap.az/Main.asmx'),
    'tile_url' => env('GOMAP_TILE_URL', 'https://gomap.az/taxitiles/mobiletile.do?guid={guid}&lng={lang}&wclr=a6cfed&x={x}&y={y}&z={z}&f=png&dp=0'),
    'timeout_seconds' => (int) env('GOMAP_TIMEOUT', 8),
    // search / address results change rarely
    'cache_seconds' => (int) env('GOMAP_CACHE_SECONDS', 86400),
];
