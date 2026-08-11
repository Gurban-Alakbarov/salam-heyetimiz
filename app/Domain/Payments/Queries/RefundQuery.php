<?php

namespace App\Domain\Payments\Queries;

use App\Domain\Payments\Models\Refund;
use App\Support\Pagination\Cursor;
use Illuminate\Support\Collection;

/** Read model for admin refund listings with cursor pagination + filters (R-API-06). */
final class RefundQuery
{
    /**
     * @param  array{status?: ?string, since?: ?string, until?: ?string, q?: ?string}  $filters
     * @return array{data: Collection, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(array $filters, int $limit, ?int $cursor): array
    {
        $query = Refund::query()
            ->with(['order:id,reference,bank_order_id,currency,payer_user_id', 'order.payer:id,phone', 'requestedByAdmin:id,email', 'processedByAdmin:id,email', 'linkedPayment']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['since'])) {
            $query->where('created_at', '>=', $filters['since']);
        }
        if (! empty($filters['until'])) {
            $query->where('created_at', '<=', $filters['until']);
        }
        if (! empty($filters['q'])) {
            $term = (string) $filters['q'];
            $query->where(function ($q) use ($term): void {
                $q->where('bank_refund_id', 'like', "%{$term}%")
                    ->orWhere('reason', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('reference', 'like', "%{$term}%")->orWhere('bank_order_id', 'like', "%{$term}%"));
            });
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
