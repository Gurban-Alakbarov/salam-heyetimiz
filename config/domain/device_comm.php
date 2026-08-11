<?php

/*
| Device-communication layer (Tech Spec §12 v1.2; Backend Arch §14.6).
| Transports: traccar (primary remote + in-person), sms (emergency fallback). ble is reserved but
| deferred off the MVP critical path (FINAL_PHASE0_VERDICT.md). CLIP / voice gateway retired.
*/
return [

    'drivers' => ['traccar', 'sms'], // 'ble' reserved, deferred

    // Transient failures eligible for ONE fallback attempt (R-GSM-04): typically Traccar offline → SMS.
    'transient_failure_codes' => ['device_offline', 'timeout', 'busy', 'network_temporary'],

    // Never trigger fallback (R-GSM-04).
    'non_transient_failure_codes' => ['device_disabled', 'driver_unsupported', 'cooldown'],

    // expected_completion_ms fallback per-driver constants when < 10 samples (R-GSM-09, HIGH-14).
    'expected_completion_ms_defaults' => [
        'traccar' => 3000, // remote command over a live Wialon session
        'sms' => 15000,    // SMS fallback
        'ble' => 1000,     // reserved (deferred)
    ],

    'expected_completion_rolling_window' => 100,

    'diagnostics_interval_hours' => 6,

    // Fallback relay command text when a device model defines none (device_models.open_command /
    // close_command take precedence — Phase 4). UMKa 310 open is cmd #25 (on-device cmdout.p 1 s
    // auto-clear); close restores the output. Sent verbatim by TraccarDriver / SmsDriver.
    'open_command' => env('DEVICE_OPEN_COMMAND', 'OUTPUT0=1'),
    'close_command' => env('DEVICE_CLOSE_COMMAND', 'OUTPUT0=0'),

    // GT06/VL110C actuation confirmation (Phase 4): after sending, the driver polls Traccar's
    // `commandResult` events for the device's 0x21 response text. Correlated by Traccar event id
    // (skew-proof — the VL110C clock can be hours off), so the lookback window can be wide.
    // NOTE: the VL110C "cut fuel" (open) reply is SLOW — it runs an on-device safety speed check
    // and answers "Cut off the fuel supply: Success! Speed: …" only ~14–15s later (the "restore"
    // close reply is ~5s). attempts×interval MUST cover that or open never confirms → stuck as
    // `dispatched`. 15×1500ms = 22.5s (well under the 120s device-comm job timeout). Only
    // confirm-via-result models (VL110C) poll; UMKa is fire-and-forget so this never affects it.
    'command_result_poll_attempts' => (int) env('DEVICE_CMD_RESULT_ATTEMPTS', 15),
    'command_result_poll_interval_ms' => (int) env('DEVICE_CMD_RESULT_INTERVAL_MS', 1500),
    'command_result_lookback_hours' => (int) env('DEVICE_CMD_RESULT_LOOKBACK_HOURS', 12),
    // A response containing any of these markers = success (incl. the idempotent "…is not running!"
    // which means the relay is already in the requested state). Everything else = failure.
    'command_result_success_markers' => ['Success!', 'is not running'],

];
