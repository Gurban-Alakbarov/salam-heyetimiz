<?php

namespace App\Http\Admin\V1\Controllers\Refunds;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Payments\Queries\RefundQuery;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\RefundResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRefundController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/refunds — adminListRefunds (refunds.view) */
    public function index(Request $request, RefundQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::REFUNDS_VIEW);

        $result = $query->adminList(
            filters: [
                'status' => $request->query('status'),
                'since' => $request->query('since'),
                'until' => $request->query('until'),
                'q' => $request->query('q'),
            ],
            limit: max(1, min(100, (int) $request->query('limit', 20))),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => RefundResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }
}
