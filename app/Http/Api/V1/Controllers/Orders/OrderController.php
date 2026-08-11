<?php

namespace App\Http\Api\V1\Controllers\Orders;

use App\Domain\Payments\Actions\CreateOrder;
use App\Domain\Payments\Actions\RecheckOrder;
use App\Domain\Payments\Models\Order;
use App\Domain\Payments\Queries\OrderQuery;
use App\Http\Api\V1\Requests\Orders\CreateOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController
{
    /** GET /v1/orders — listMyOrders */
    public function index(Request $request, OrderQuery $query): JsonResponse
    {
        $result = $query->forUser(
            userId: (int) $request->user()->getKey(),
            status: $request->query('status'),
            limit: $this->limit($request),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => OrderResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** POST /v1/orders — createOrder (Idempotency-Key required) */
    public function store(CreateOrderRequest $request, CreateOrder $action): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => __('errors.idempotency_key_required'),
            ]);
        }

        $order = $action->handle($request->user(), $request->toData(), $key);

        return (new OrderResource($order->load('items')))
            ->response()
            ->setStatusCode(201);
    }

    /** GET /v1/orders/{orderId} — getOrder */
    public function show(Request $request, int $orderId): OrderDetailResource
    {
        $order = Order::query()->findOrFail($orderId);

        // Non-owners get 404 (never another user's order — R-PAY-12 / HIGH-16).
        abort_unless($request->user()?->can('view', $order) ?? false, 404);

        $order->load(['items', 'payments', 'refunds', 'callbacks']);

        return new OrderDetailResource($order);
    }

    /** POST /v1/orders/{orderId}/recheck — recheckOrder */
    public function recheck(Request $request, int $orderId, RecheckOrder $action): OrderResource
    {
        $order = Order::query()->findOrFail($orderId);

        abort_unless($request->user()?->can('recheck', $order) ?? false, 404);

        return new OrderResource($action->handle($order)->load('items'));
    }

    private function limit(Request $request): int
    {
        return max(1, min(100, (int) $request->query('limit', 20)));
    }
}
