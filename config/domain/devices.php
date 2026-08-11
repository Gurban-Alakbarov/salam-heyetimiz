<?php

/*
| Device-domain tunables. Operational values are seeded into the `settings` table
| (audited, admin-tunable per R-DOM-09); the values here are the config-time
| defaults / fallbacks (R-CODE-07). Tech Spec Appendix B.
*/
return [

    'cooldowns' => [
        'user_device_seconds' => 5,   // per-(user, device) — R-DOM, Tech Spec App. B
        'device_global_seconds' => 2, // per-device
    ],

    'offline_threshold_hours' => 24, // legacy diagnose() window (stale-device cleanup)

    // A device is "online" in the admin panel only if telemetry arrived within this window. The UMKa
    // reports at most every ~5 min (period.max 300s + keepalive), so 15 min (3×) avoids flapping while
    // reflecting the real Traccar-fed connectivity (last_online_at is updated by the event-forward webhook).
    'offline_threshold_minutes' => 15,

    'diagnostics_interval_hours' => 6,

    'whitelist' => [
        'default_capacity' => 100,          // device_models.whitelist_capacity NOT NULL default (R-DOM-14)
        'max_pending_changes' => 5,         // burst guard → 409 too_many_pending_changes (R-GSM-08)
        'priority_routine' => 50,
        'priority_admin_resync' => 10,      // lower drains first
        'max_sync_attempts' => 5,
    ],

    // Barrier photo upload (admin-managed). Files land on the `public` disk under `devices/`
    // with a random UUID name; the DB `image_url` column stores the resulting public URL.
    'image' => [
        'disk' => 'public',
        'dir' => 'devices',
        'max_kilobytes' => 5120,            // 5 MB — the `max:` validation rule (KB)
        'max_dimension' => 1600,            // longest side (px) — server-side downscale
        'webp_quality' => 82,
        'accept_ext' => ['jpg', 'jpeg', 'png', 'webp'],
        'accept_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        // Public base for stored URLs. Null → config('app.url'). Prod: https://api.salamheyetimiz.com
        'base_url' => env('DEVICE_IMAGE_BASE_URL'),
    ],

];
