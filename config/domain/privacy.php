<?php

/*
| Privacy / anonymisation policy (Tech Spec §3.7; HIGH-12; DB Arch §0.3).
*/
return [

    // PII is anonymised IMMEDIATELY on soft-delete (R-DOM-02 / HIGH-12).
    'anonymise_immediately_on_soft_delete' => true,

    // The 30-day window protects the reactivation right, not the data.
    'reactivation_window_days' => 30,

    // Deterministic token: deleted:<sha256(original)> — lets support find the ghost by hash.
    'hash_algo' => 'sha256',
    'anonymised_prefix' => 'deleted:',

    // Data-subject export link TTL.
    'export_url_ttl_hours' => 72,

];
