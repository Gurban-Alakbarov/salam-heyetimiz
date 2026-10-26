<?php

/*
| Privacy / anonymisation policy (Tech Spec §3.7; HIGH-12; DB Arch §0.3).
|
| Y7 (2026-10-26) — what the code actually does (App\Domain\Users\Services\AccountAnonymiser), on every account
| deletion (self-service DELETE /v1/me or admin) and via `users:anonymise-deleted` for older deletions:
|  - phone → `del:<user id>` (users.phone is VARCHAR(20): a sha256 token does not fit; the id keeps it unique);
|  - email, full name, verification time, last login IP → null; installs: push token + IP cleared, revoked;
|  - sha256 of the original phone / email goes ONLY to the audit log (resident.deleted), so support can still
|    match a ghost by hash without the data being kept.
| The phone and email are free to register again at once. There is no reactivation: once anonymised the
| account cannot be restored (the reactivation window of the original spec is not implemented).
*/
return [

    // PII is anonymised IMMEDIATELY on soft-delete (R-DOM-02 / HIGH-12) — always, not configurable.
    'anonymise_immediately_on_soft_delete' => true,

    // Hash used for the audit-only fingerprints of the erased phone / email.
    'hash_algo' => 'sha256',

    // Replacement for users.phone (+ the user id).
    'anonymised_prefix' => 'del:',

    // Data-subject export link TTL (export not implemented yet).
    'export_url_ttl_hours' => 72,

];
