<?php

namespace App\Http\Admin\V1\Controllers\Subscriptions;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\SubscriptionResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/subscriptions — adminListSubscriptions (subscriptions.view) */
    public function index(Request $request, SubscriptionQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::SUBSCRIPTIONS_VIEW);

        $expiresWithinDays = $request->query('expires_within_days');

        $result = $query->adminList(
            status: $request->query('status'),
            expiresWithinDays: $expiresWithinDays !== null ? (int) $expiresWithinDays : null,
            limit: max(1, min(100, (int) $request->query('limit', 20))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => SubscriptionResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }
}
