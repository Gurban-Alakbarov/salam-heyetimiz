<?php

namespace App\Domain\Users\Queries;

use Illuminate\Support\Facades\DB;

/**
 * K1 — does an account carry LIVE access someone could take over by claiming it? Live = owns a device, an
 * active roster row (device access), an active complex membership (may subscribe to complex gates), an
 * active family link (head or member), or a linked admin (Komendant). History alone (revoked rows, old
 * orders) is not live: such a row may still be continued by a new registration, as before.
 */
final class AccountAccessFootprint
{
    public function isLive(int $userId): bool
    {
        return DB::table('devices')->where('owner_user_id', $userId)->whereNull('deleted_at')->exists()
            || DB::table('device_users')->where('user_id', $userId)->where('status', 'active')->exists()
            || DB::table('complex_members')->where('user_id', $userId)->where('status', 'active')->exists()
            || DB::table('family_links')->where('status', 'active')
                ->where(fn ($q) => $q->where('head_user_id', $userId)->orWhere('member_user_id', $userId))->exists()
            || DB::table('admin_users')->where('user_id', $userId)->exists();
    }
}
