<?php

/*
| Invitation engine tunables (IMPLEMENTATION_PLAN §9 / B3; BR-10).
*/
return [
    // A link is valid for 7 days from creation / the latest resend.
    'ttl_days' => 7,

    // Resend: at most once a minute per invitation, at most 5 sends a day per invitation.
    'resend_cooldown_seconds' => 60,
    'resend_max_per_day' => 5,

    // Sends (create + resend) per inviter per day — a single Komendant / family head.
    'inviter_max_per_day' => 30,

    // Public landing link base; the plaintext token is appended (universal link in B6/B13).
    'link_base' => env('INVITATION_LINK_BASE', rtrim((string) env('APP_URL', 'https://salamheyetimiz.com'), '/').'/invite'),
];
