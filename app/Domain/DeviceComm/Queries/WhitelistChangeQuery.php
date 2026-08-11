<?php

namespace App\Domain\DeviceComm\Queries;

use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Support\Pagination\Cursor;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Whitelist outbox read model (adminWhitelistQueue) — pending + recent changes per device. */
final class WhitelistChangeQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forDevice(int $deviceId, ?string $status, int $limit, ?int $cursor): array
    {
        $query = WhitelistChange::query()->where('device_id', $deviceId);

        if ($status !== null) {
            $query->where('status', $status);
        }

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
