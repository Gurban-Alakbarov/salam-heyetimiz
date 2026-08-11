<?php

/*
| Notification-domain tunables (Tech Spec §17). Channel bitmask: push=1, sms=2,
| inapp=4, email=8 (DB Arch §7.1 default_channels_mask). Template bodies live in
| notification_template_locales (DB), not in lang files (R-LOC-03).
*/
return [

    'channels' => [
        'push' => 1,
        'sms' => 2,
        'inapp' => 4,
        'email' => 8,
    ],

    // Categories that the user may mute (others are forced — security/billing).
    'mutable_categories' => ['marketing'],

    // Email channel is P2 (Tech Spec §17.1) — disabled at MVP.
    'email_enabled' => false,

    'payload_max_bytes' => 4096, // MED-06 guard

];
