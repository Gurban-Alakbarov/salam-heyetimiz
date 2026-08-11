<?php

namespace App\Domain\Visitor\Queries;

use App\Domain\Visitor\Models\VisitorLink;
use App\Support\Pagination\Cursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read model for the admin visitor-links directory: filter by device / derived status / creator / purpose
 * / access type / name / date range, complex-scoped for complex_manager, cursor-paginated by id. Status is
 * derived from revoked_at / expires_at / usage, so those filters mirror VisitorLink's predicates in SQL.
 */
final class VisitorLinkQuery
{
    /**
     * @return array{data: Collection<int, VisitorLink>, page: array{next_cursor: ?string, has_more: bool, limit: int}}
     */
    public function adminList(VisitorLinkListFilters $filters, ?int $scopeComplexId, int $limit, ?int $cursor): array
    {
        $now = Carbon::now();

        $query = VisitorLink::query()
            ->with(['device', 'createdByUser', 'createdByAdmin'])
            ->when($filters->deviceId !== null, fn (Builder $q) => $q->where('device_id', $filters->deviceId))
            ->when($scopeComplexId !== null, fn (Builder $q) => $q->whereHas('device', fn (Builder $d) => $d->where('complex_id', $scopeComplexId)))
            ->when($cursor !== null, fn (Builder $q) => $q->where('id', '<', $cursor));

        $this->applyStatus($query, $filters->status, $now);
        $this->applyFilters($query, $filters);

        $rows = $query->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return [
            'data' => $data,
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->getKey()) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @param  Builder<VisitorLink>  $query
     */
    private function applyStatus(Builder $query, ?string $status, Carbon $now): void
    {
        match ($status) {
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', $now),
            'active' => $query->whereNull('revoked_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
                ->where(fn (Builder $q) => $q->whereNull('max_usage')->orWhereColumn('usage_count', '<', 'max_usage')),
            default => null, // all
        };
    }

    /**
     * @param  Builder<VisitorLink>  $query
     */
    private function applyFilters(Builder $query, VisitorLinkListFilters $filters): void
    {
        $query
            ->when($filters->q !== null && $filters->q !== '', fn (Builder $q) => $q->where('visitor_name', 'like', '%'.$filters->q.'%'))
            ->when($filters->purpose !== null, fn (Builder $q) => $q->where('purpose', $filters->purpose))
            ->when($filters->accessType !== null, fn (Builder $q) => $q->where('access_type', $filters->accessType))
            ->when($filters->createdBy === 'admin', fn (Builder $q) => $q->whereNotNull('created_by_admin_id'))
            ->when($filters->createdBy === 'resident', fn (Builder $q) => $q->whereNotNull('created_by_user_id'))
            ->when($filters->createdFrom !== null, fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($filters->createdFrom)->startOfDay()))
            ->when($filters->createdTo !== null, fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($filters->createdTo)->endOfDay()));
    }
}
