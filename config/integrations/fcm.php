<?php

/*
| Firebase Cloud Messaging — push for both Android and iOS via FCM (Tech Spec §17.1).
*/
return [

    'project_id' => env('FCM_PROJECT_ID'),
    'credentials_path' => env('FCM_CREDENTIALS_PATH'),

    // 'fake' (or the testing env / missing credentials) → FakePushClient; empty → real FcmPushClient.
    'driver' => env('FCM_DRIVER'),

    'timeout_seconds' => (int) env('FCM_TIMEOUT', 10),

];
