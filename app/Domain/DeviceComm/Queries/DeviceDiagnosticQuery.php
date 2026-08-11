<?php

namespace App\Domain\DeviceComm\Queries;

use App\Domain\DeviceComm\Models\DeviceDiagnostic;
use App\Support\Pagination\Cursor;
use Illuminate\Support\Collection;

/** Diagnostics history read model (adminDeviceDiagnostics) — cursor-paginated, newest first. */
final class DeviceDiagnosticQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function forDevice(int $deviceId, int $limit, ?int $cursor): array
    {
        $query = DeviceDiagnostic::query()->where('device_id', $deviceId);

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
