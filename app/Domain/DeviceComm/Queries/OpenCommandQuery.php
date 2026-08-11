<?php

namespace App\Domain\DeviceComm\Queries;

use App\Domain\DeviceComm\Models\OpenCommand;
use App\Support\Pagination\Cursor;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Command history read models with cursor pagination (R-API-06). The mobile list is scoped to the
 * caller; the admin list spans all users on a device. Partition pruning happens via the date filters.
 */
final class OpenCommandQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forUserDevice(int $userId, int $deviceId, ?string $state, ?string $since, ?string $until, int $limit, ?int $cursor): array
    {
        $query = OpenCommand::query()
            ->where('device_id', $deviceId)
            ->where('user_id', $userId);

        return $this->paginate($this->applyFilters($query, $state, $since, $until), $limit, $cursor);
    }

    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminForDevice(int $deviceId, ?string $state, ?string $since, ?string $until, int $limit, ?int $cursor): array
    {
        $query = OpenCommand::query()->where('device_id', $deviceId);

        return $this->paginate($this->applyFilters($query, $state, $since, $until), $limit, $cursor);
    }

    private function applyFilters(Builder $query, ?string $state, ?string $since, ?string $until): Builder
    {
        if ($state !== null) {
            $query->where('state', $state);
        }
        if ($since !== null) {
            $query->where('requested_at', '>=', $since);
        }
        if ($until !== null) {
            $query->where('requested_at', '<=', $until);
        }

        return $query;
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
