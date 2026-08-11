<?php

namespace App\Domain\Notifications\Queries;

use App\Domain\Notifications\Models\NotificationCampaign;
use App\Support\Pagination\Cursor;
use Illuminate\Support\Collection;

/**
 * Read model for admin notification-campaign listings (R-API-06). A complex_manager is scoped to the
 * campaigns it created — its own complex's campaigns, since the schema carries no complex_id and a
 * complex_manager's every campaign is complex-scoped at send. Global roles (super/operator) see all.
 */
final class NotificationCampaignQuery
{
    /**
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(?string $status, ?int $createdByAdminId, int $limit, ?int $cursor): array
    {
        $query = NotificationCampaign::query();

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }
        if ($createdByAdminId !== null) {
            $query->where('created_by_admin_id', $createdByAdminId);
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
