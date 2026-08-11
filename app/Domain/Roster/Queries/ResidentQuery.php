<?php

namespace App\Domain\Roster\Queries;

use App\Support\Pagination\Cursor;
use Illuminate\Support\Facades\DB;

/**
 * Residents read model (admin): one row per active roster membership — who the resident is, which complex
 * they live in (via the device), their device's status/connectivity, role, and subscription status. Scoped
 * to a complex for complex_manager. Cursor-paginated by device_user id (R-API-06).
 */
final class ResidentQuery
{
    /**
     * @return array{data: array<int, array<string, mixed>>, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(?int $complexId, ?string $q, ?string $role, ?string $status, ?int $scopeComplexId, int $limit, ?int $cursor): array
    {
        $query = DB::table('device_users as du')
            ->join('users as u', 'u.id', '=', 'du.user_id')
            ->join('devices as d', 'd.id', '=', 'du.device_id')
            ->leftJoin('complexes as c', 'c.id', '=', 'd.complex_id')
            ->leftJoin('subscriptions as s', 's.device_user_id', '=', 'du.id')
            ->whereNull('u.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('du.status', 'active')
            ->select([
                'du.id as device_user_id', 'du.user_id', 'u.phone', 'u.email', 'u.full_name', 'du.role', 'du.last_open_at',
                'd.id as device_id', 'd.serial', 'd.status as device_status', 'd.last_online_at',
                'c.id as complex_id', 'c.name as complex_name',
                's.status as sub_status', 's.ends_at as sub_ends_at',
            ]);

        // complex_manager is locked to its complex; the optional filter narrows further within scope.
        if ($scopeComplexId !== null) {
            $query->where('d.complex_id', $scopeComplexId);
        }
        if ($complexId !== null) {
            $query->where('d.complex_id', $complexId);
        }
        // Free-text search spans phone, email and name so an admin can find a resident by any of them.
        if ($q !== null && $q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('u.phone', 'like', '%'.$q.'%')
                    ->orWhere('u.email', 'like', '%'.$q.'%')
                    ->orWhere('u.full_name', 'like', '%'.$q.'%');
            });
        }
        if ($role !== null && $role !== '') {
            $query->where('du.role', $role);
        }
        if ($status !== null && $status !== '') {
            $query->where('s.status', $status);
        }
        if ($cursor !== null) {
            $query->where('du.id', '<', $cursor);
        }

        $rows = $query->orderByDesc('du.id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data->all(),
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->device_user_id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Distinct resident user_ids matching an admin-campaign audience filter (ADMIN_SPEC). Reuses the same
     * active-roster join as the directory listing; the complex_manager scope is an ANDed WHERE so a scoped
     * caller can never resolve outside its complex. Recipients dedupe to distinct user_id (a user on two
     * devices = one recipient). `$userIds` constrains to an explicit id list (an empty list → no recipients).
     *
     * @param  array<int, int>|null  $userIds
     * @return array<int, int>
     */
    public function resolveAudienceUserIds(?int $complexId, ?string $q, ?string $role, ?string $status, ?array $userIds, ?int $scopeComplexId): array
    {
        $query = DB::table('device_users as du')
            ->join('users as u', 'u.id', '=', 'du.user_id')
            ->join('devices as d', 'd.id', '=', 'du.device_id')
            ->leftJoin('subscriptions as s', 's.device_user_id', '=', 'du.id')
            ->whereNull('u.deleted_at')
            ->whereNull('d.deleted_at')
            ->where('du.status', 'active');

        if ($scopeComplexId !== null) {
            $query->where('d.complex_id', $scopeComplexId);
        }
        if ($complexId !== null) {
            $query->where('d.complex_id', $complexId);
        }
        if ($q !== null && $q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('u.phone', 'like', '%'.$q.'%')
                    ->orWhere('u.email', 'like', '%'.$q.'%')
                    ->orWhere('u.full_name', 'like', '%'.$q.'%');
            });
        }
        if ($role !== null && $role !== '') {
            $query->where('du.role', $role);
        }
        if ($status !== null && $status !== '') {
            $query->where('s.status', $status);
        }
        if ($userIds !== null) {
            // An explicit empty list resolves to no recipients (whereIn [] would otherwise match all).
            $query->whereIn('du.user_id', $userIds === [] ? [0] : $userIds);
        }

        return $query->distinct()->pluck('du.user_id')->map(static fn ($v): int => (int) $v)->all();
    }
}
