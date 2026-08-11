<?php

namespace App\Http\Admin\V1\Controllers\Orders;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Payments\Actions\RecheckOrder;
use App\Domain\Payments\Actions\RequestRefund;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Queries\OrderQuery;
use App\Http\Admin\V1\Requests\Orders\RefundOrderRequest;
use App\Http\Concerns\AuthorizesAdmin;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\RefundResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminOrderController
{
    use AuthorizesAdmin;

    /** GET /admin/v1/orders — adminListOrders (orders.view) */
    public function index(Request $request, OrderQuery $query): JsonResponse
    {
        $this->requirePermission($request, Permission::ORDERS_VIEW);

        $result = $query->adminList(
            status: $request->query('status'),
            purpose: $request->query('purpose'),
            payerUserId: $request->query('payer_user_id') !== null ? (int) $request->query('payer_user_id') : null,
            since: $request->query('since'),
            until: $request->query('until'),
            limit: max(1, min(100, (int) $request->query('limit', 20))),
            cursor: Cursor::decode($request->query('cursor')),
            q: $request->query('q'),
        );

        return response()->json([
            'data' => OrderResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /admin/v1/orders/{orderId} — adminGetOrder (orders.view) */
    public function show(Request $request, int $orderId): OrderDetailResource
    {
        $this->requirePermission($request, Permission::ORDERS_VIEW);

        $order = Order::query()->findOrFail($orderId);
        $order->load(['items', 'payments', 'refunds', 'callbacks', 'payer:id,phone,email']);

        return new OrderDetailResource($order);
    }

    /** POST /admin/v1/orders/{orderId}/refund — adminRefundOrder (refunds.create, Idempotency-Key) */
    public function refund(RefundOrderRequest $request, int $orderId, RequestRefund $action, \App\Domain\Audit\Services\AuditLogger $audit): JsonResponse
    {
        $this->requirePermission($request, Permission::REFUNDS_CREATE);

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw ValidationException::withMessages(['Idempotency-Key' => __('errors.idempotency_key_required')]);
        }

        $order = Order::query()->findOrFail($orderId);

        $refund = $action->handle($order, $request->toData(), $request->user());

        // Rich audit of the request (actor + ip + user-agent auto-captured by AuditLogger).
        $audit->record('refund.requested', [
            'refund_id' => (int) $refund->id,
            'order_id' => (int) $order->id,
            'bank_order_id' => $order->bank_order_id,
            'amount_minor' => (int) $refund->amount_minor,
            'reason' => $refund->reason,
        ]);

        return (new RefundResource($refund))->response()->setStatusCode(201);
    }

    /** POST /admin/v1/orders/{orderId}/recheck — adminRecheckOrder (orders.view) */
    public function recheck(Request $request, int $orderId, RecheckOrder $action): OrderResource
    {
        $this->requirePermission($request, Permission::ORDERS_VIEW);

        $order = Order::query()->findOrFail($orderId);

        return new OrderResource($action->handle($order)->load('items'));
    }
}
