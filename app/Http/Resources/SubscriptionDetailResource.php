<?php

namespace App\Http\Resources;

use App\Domain\Subscriptions\Models\SubscriptionPeriod;
use Illuminate\Http\Request;

/**
 * Maps to openapi components/schemas/SubscriptionDetail
 * (Subscription + periods[] + latest_order). Requires the `periods.order`
 * relation to be eager-loaded by the controller.
 */
class SubscriptionDetailResource extends SubscriptionResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latestFunding = $this->periods
            ->filter(fn (SubscriptionPeriod $period): bool => $period->kind->isFunding())
            ->sortByDesc('id')
            ->first();

        $latestOrder = $latestFunding?->order;

        return array_merge(parent::toArray($request), [
            'periods' => SubscriptionPeriodResource::collection(
                $this->periods->sortByDesc('id')->values()
            ),
            'latest_order' => $latestOrder !== null
                ? (new OrderResource($latestOrder))->toArray($request)
                : null,
        ]);
    }
}
