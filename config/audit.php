<?php

/*
| Audit & retention policy (Tech Spec §16; DB Arch §8.1). The audit_log is
| append-only and immutable: the runtime DB role has INSERT/SELECT only; UPDATE
| and DELETE require the migrator role. Enforced by ci:grants:audit-log-immutable
| (R-SEC-12) and a daily production monitor.
*/
return [

    'retention' => [
        'audit_log_years' => 5,
        'payment_logs_years' => 5,
        'payment_callbacks_years' => 5,
        'open_commands_months' => 24,
        'device_diagnostics_months' => 12,
        'notifications_inapp_months' => 12,
        'notifications_other_days' => 90,
    ],

    'hot_partition_months' => 12,

    'db_roles' => [
        'runtime' => env('DB_RUNTIME_ROLE', 'salam_runtime'),
        'migrator' => env('DB_MIGRATOR_ROLE', 'salam_migrator'),
    ],

    // Tables whose immutability the CI grant check & daily monitor verify.
    'immutable_tables' => ['audit_log', 'payment_logs'],

];
