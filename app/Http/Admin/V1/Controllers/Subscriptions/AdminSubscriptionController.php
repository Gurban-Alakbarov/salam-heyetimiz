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

    /**
     * GET /admin/v1/subscriptions/{id} — adminGetSubscription (subscriptions.view; complex scope → 404). B11:
     * the list shape + why it ended (cancellation_reason) + the legacy-comp flag (BR-17: price 0, never paid)
     * + the period ledger with the payer of each period (payer ≠ beneficiary, B2).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->requirePermission($request, Permission::SUBSCRIPTIONS_VIEW);
        /** @var \App\Domain\Subscriptions\Models\Subscription $sub */
        $sub = \App\Domain\Subscriptions\Models\Subscription::query()->with(['deviceUser.device', 'deviceUser.user', 'periods'])->findOrFail($id);
        $device = $sub->deviceUser?->device;
        $scope = $this->complexScopeId($request);
        abort_if($scope !== null && (int) ($device?->complex_id ?? 0) !== $scope, 404);

        $periods = $sub->periods->sortBy('id')->values();

        return response()->json(['data' => (new SubscriptionResource($sub))->toArray($request) + [
            'cancelled_at' => optional($sub->cancelled_at)->toIso8601String(),
            'cancellation_reason' => $sub->cancellation_reason,
            'is_legacy_comp' => (int) $sub->price_minor === 0 && ! $periods->contains(fn ($p) => (int) $p->amount_minor > 0),
            'device' => $device === null ? null : [
                'id' => (int) $device->id, 'serial' => $device->serial, 'location_label' => $device->location_label,
                'ownership_mode' => $device->ownership_mode->value, 'complex_id' => $device->complex_id !== null ? (int) $device->complex_id : null,
            ],
            'beneficiary' => $sub->deviceUser?->user === null ? null : [
                'id' => (int) $sub->deviceUser->user->id, 'full_name' => $sub->deviceUser->user->full_name, 'phone' => $sub->deviceUser->user->phone,
            ],
            'family_link_id' => $sub->deviceUser?->family_link_id !== null ? (int) $sub->deviceUser->family_link_id : null,
            'periods' => $periods->map(fn ($p): array => [
                'id' => (int) $p->id,
                'kind' => $p->kind->value,
                'period_start' => optional($p->period_start)->toIso8601String(),
                'period_end' => optional($p->period_end)->toIso8601String(),
                'amount_minor' => (int) $p->amount_minor,
                'order_id' => $p->order_id !== null ? (int) $p->order_id : null,
                'paid_by_user_id' => $p->paid_by_user_id !== null ? (int) $p->paid_by_user_id : null,
            ])->all(),
        ]]);
    }
}
