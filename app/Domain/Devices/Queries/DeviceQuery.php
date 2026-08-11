<?php

namespace App\Domain\Devices\Queries;

use App\Domain\Devices\Enums\DeviceListFilter;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Support\Pagination\Cursor;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Device read models with cursor pagination (R-API-06). The mobile list is scoped to the caller's
 * roster (owned + member); the admin list spans all devices with status/owner/region/text filters.
 */
final class DeviceQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forUser(int $userId, DeviceListFilter $filter, int $limit, ?int $cursor): array
    {
        $query = Device::query()->with(['model', 'region']);

        $memberOf = fn (Builder $q) => $q->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value);

        match ($filter) {
            DeviceListFilter::Owned => $query->where('owner_user_id', $userId),
            DeviceListFilter::Member => $query->whereHas('deviceUsers', fn (Builder $q) => $memberOf($q)
                ->where('role', DeviceUserRole::User->value)),
            DeviceListFilter::Suspended => $query
                ->where('status', DeviceStatus::Suspended->value)
                ->where(fn (Builder $q) => $q->where('owner_user_id', $userId)
                    ->orWhereHas('deviceUsers', $memberOf)),
            DeviceListFilter::All => $query->where(fn (Builder $q) => $q->where('owner_user_id', $userId)
                ->orWhereHas('deviceUsers', $memberOf)),
        };

        return $this->paginate($query, $limit, $cursor);
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(?string $status, ?int $ownerUserId, ?int $regionId, ?string $q, int $limit, ?int $cursor, ?int $complexId = null): array
    {
        $query = Device::query()->with(['model', 'region', 'simOperator', 'owner']);

        // Decommissioned devices are soft-deleted; surface them only when explicitly requested.
        if ($status === DeviceStatus::Decommissioned->value) {
            $query->withTrashed();
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        // complex_manager scope: only devices in the caller's complex.
        if ($complexId !== null) {
            $query->where('complex_id', $complexId);
        }

        if ($ownerUserId !== null) {
            $query->where('owner_user_id', $ownerUserId);
        }

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        if ($q !== null && $q !== '') {
            $query->where(fn (Builder $b) => $b
                ->where('serial', 'like', '%'.$q.'%')
                ->orWhere('sim_phone', 'like', '%'.$q.'%'));
        }

        return $this->paginate($query, $limit, $cursor);
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    private function paginate(Builder $query, int $limit, ?int $cursor): array
    {
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data,
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }
}
