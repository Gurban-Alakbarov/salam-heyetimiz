<?php

namespace App\Http\Admin\V1\Controllers\Residents;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Users\Models\ResidentArchive;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O10 — the archive of residents an admin deleted from the system (residents.archive.view; super_admin unless
 * granted): their personal data as it was, who deleted them and when, and what they had (snapshot). Read-only;
 * rows are purged after privacy.resident_archive_years.
 */
class AdminResidentArchiveController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/resident-archive?q=&cursor=&limit= — adminListResidentArchive. Newest first. */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::RESIDENTS_ARCHIVE_VIEW);

        $limit = max(1, min(100, (int) $request->query('limit', 25)));
        $cursor = Cursor::decode($request->query('cursor'));
        $q = trim((string) $request->query('q', ''));

        $rows = ResidentArchive::query()
            ->with('deletedByAdmin:id,email,name')
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('full_name', 'like', $like)
                    ->when(ctype_digit($q), fn ($d) => $d->orWhere('user_id', (int) $q)));
            })
            ->when($cursor !== null, fn ($query) => $query->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return response()->json([
            'data' => $data->map(fn (ResidentArchive $a): array => [
                'id' => (int) $a->id,
                'user_id' => (int) $a->user_id,
                'full_name' => $a->full_name,
                'phone' => $a->phone,
                'email' => $a->email,
                'account_type' => $a->account_type,
                'registered_at' => $a->registered_at?->toIso8601String(),
                'last_login_at' => $a->last_login_at?->toIso8601String(),
                'reason' => $a->reason,
                'deleted_by' => $a->deletedByAdmin !== null
                    ? ['id' => (int) $a->deletedByAdmin->id, 'email' => $a->deletedByAdmin->email, 'name' => $a->deletedByAdmin->name]
                    : null,
                'archived_at' => $a->archived_at->toIso8601String(),
                'purge_after' => $a->purge_after->toIso8601String(),
                'snapshot' => $a->snapshot,
            ])->all(),
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }
}
