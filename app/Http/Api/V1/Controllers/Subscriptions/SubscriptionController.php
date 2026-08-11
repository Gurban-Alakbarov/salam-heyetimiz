<?php

namespace App\Http\Api\V1\Controllers\Subscriptions;

use App\Domain\Subscriptions\Actions\RenewSubscription;
use App\Domain\Subscriptions\Actions\ToggleAutoRenew;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Queries\SubscriptionQuery;
use App\Http\Api\V1\Requests\Subscriptions\RenewSubscriptionRequest;
use App\Http\Api\V1\Requests\Subscriptions\ToggleAutoRenewRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\SubscriptionDetailResource;
use App\Http\Resources\SubscriptionResource;
use App\Support\Pagination\Cursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController
{
    /** GET /v1/subscriptions — listMySubscriptions */
    public function index(Request $request, SubscriptionQuery $query): JsonResponse
    {
        $result = $query->forUser(
            userId: (int) $request->user()->getKey(),
            status: $request->query('status'),
            limit: $this->limit($request),
            cursor: Cursor::decode($request->query('cursor')),
        );

        return response()->json([
            'data' => SubscriptionResource::collection($result['data']),
            'page' => $result['page'],
        ]);
    }

    /** GET /v1/subscriptions/{subscriptionId} — getSubscription */
    public function show(Request $request, int $subscriptionId): SubscriptionDetailResource
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        // Non-owners get 404 (never another user's subscription).
        abort_unless($request->user()?->can('view', $subscription) ?? false, 404);

        $subscription->load(['deviceUser', 'periods.order.items']);

        return new SubscriptionDetailResource($subscription);
    }

    /** POST /v1/subscriptions/{subscriptionId}/renew — renewSubscription (Idempotency-Key required) */
    public function renew(RenewSubscriptionRequest $request, int $subscriptionId, RenewSubscription $action): JsonResponse
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        abort_unless($request->user()?->can('renew', $subscription) ?? false, 404);

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw ValidationException::withMessages([
                'Idempotency-Key' => __('errors.idempotency_key_required'),
            ]);
        }

        $order = $action->handle(
            subscription: $subscription,
            payer: $request->user(),
            returnUrl: $request->toData()->returnUrl,
            idempotencyKey: $key,
        );

        // openapi renewSubscription → 200 (the order may be brand-new or an idempotent replay).
        return (new OrderResource($order->load('items')))
            ->response()
            ->setStatusCode(200);
    }

    /** PATCH /v1/subscriptions/{subscriptionId}/auto-renew — toggleAutoRenew */
    public function toggleAutoRenew(ToggleAutoRenewRequest $request, int $subscriptionId, ToggleAutoRenew $action): SubscriptionResource
    {
        $subscription = Subscription::query()->findOrFail($subscriptionId);

        abort_unless($request->user()?->can('toggleAutoRenew', $subscription) ?? false, 404);

        $data = $request->toData();

        $updated = $action->handle($subscription, $data->autoRenew, $data->cardTokenId);

        return new SubscriptionResource($updated->load('deviceUser'));
    }

    private function limit(Request $request): int
    {
        return max(1, min(100, (int) $request->query('limit', 20)));
    }
}
