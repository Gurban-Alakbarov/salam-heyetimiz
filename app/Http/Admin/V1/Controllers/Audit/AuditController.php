<?php

namespace App\Http\Admin\V1\Controllers\Audit;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Audit\Models\AuditLog;
use App\Http\Concerns\AuthorizesAdmin;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read the administrative audit trail (audit.view). Filterable by action / actor / date, cursor-paginated. */
class AuditController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/audit — adminListAudit (audit.view). */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, Permission::AUDIT_VIEW);

        $limit = max(1, min(100, (int) $request->query('limit', 30)));
        $cursor = Cursor::decode($request->query('cursor'));

        $query = AuditLog::query()->orderByDesc('id');

        if (($action = $request->query('action')) !== null) {
            $query->where('action', $action);
        }
        // Phase 2 — trace one area / one admin / one object: "device." → every device action.
        if (($prefix = $request->query('action_prefix')) !== null && $prefix !== '') {
            $query->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $prefix).'%');
        }
        if (($actorId = $request->query('actor_id')) !== null && ctype_digit((string) $actorId)) {
            $query->where('actor_id', (int) $actorId);
        }
        if (($type = $request->query('auditable_type')) !== null && $type !== '') {
            // short names from the panel ("Device", "User", …) or the full class
            $query->where(fn ($w) => $w->where('auditable_type', $type)->orWhere('auditable_type', 'like', '%\\'.$type));
        }
        if (($auditableId = $request->query('auditable_id')) !== null && ctype_digit((string) $auditableId)) {
            $query->where('auditable_id', (int) $auditableId);
        }
        if (($q = trim((string) $request->query('q', ''))) !== '') {
            // free text over who did it and the payload (ids, phones, codes …)
            $query->where(fn ($w) => $w->where('actor_label', 'like', "%{$q}%")
                ->orWhere('action', 'like', "%{$q}%")
                ->orWhere('payload', 'like', "%{$q}%"));
        }
        if (($actorKind = $request->query('actor_kind')) !== null) {
            $query->where('actor_kind', $actorKind);
        }
        if (($since = $request->query('since')) !== null) {
            $query->where('created_at', '>=', $since);
        }
        if (($until = $request->query('until')) !== null) {
            $query->where('created_at', '<=', $until);
        }
        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return response()->json([
            'data' => $data->map(fn (AuditLog $log): array => [
                'id' => (int) $log->id,
                'actor_kind' => $log->actor_kind,
                'actor_id' => $log->actor_id !== null ? (int) $log->actor_id : null,
                'actor_label' => $log->actor_label,
                'impersonator_admin_id' => $log->impersonator_admin_id !== null ? (int) $log->impersonator_admin_id : null,
                'action' => $log->action,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id !== null ? (int) $log->auditable_id : null,
                'payload' => $log->payload,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->all(),
            'page' => [
                'next_cursor' => $hasMore && $data->isNotEmpty() ? Cursor::encode((int) $data->last()->id) : null,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }
}
