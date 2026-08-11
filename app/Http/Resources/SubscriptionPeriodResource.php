<?php

namespace App\Http\Resources;

use App\Domain\Subscriptions\Models\SubscriptionPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPeriod
 *
 * Maps to openapi components/schemas/SubscriptionPeriod.
 */
class SubscriptionPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'kind' => $this->kind->value,
            'period_start' => optional($this->period_start)->toIso8601String(),
            'period_end' => optional($this->period_end)->toIso8601String(),
            'amount_minor' => (int) $this->amount_minor,
            'order_id' => (int) $this->order_id,
        ];
    }
}
