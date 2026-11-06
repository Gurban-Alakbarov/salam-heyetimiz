<?php

return [
    // Where built copies of the website live. `current` in here is what the web server serves.
    'releases_path' => env('SITE_RELEASES_PATH', storage_path('app/site-releases')),
];
