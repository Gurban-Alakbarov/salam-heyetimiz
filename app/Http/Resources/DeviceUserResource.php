<?php

namespace App\Http\Resources;

use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceUser
 *
 * Maps to openapi components/schemas/DeviceUser (roster row). The `subscription` brief is attached
 * to the model by the caller (resolved via the Subscriptions read model — R-ARCH-06).
 */
class DeviceUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'user' => new UserBriefResource($this->whenLoaded('user')),
            'role' => $this->role->value,
            'status' => $this->status->value,
            'added_at' => optional($this->created_at)->toIso8601String(),
            'last_open_at' => optional($this->last_open_at)->toIso8601String(),
            'subscription' => $this->subscription_brief !== null
                ? (new SubscriptionBriefResource($this->subscription_brief))->toArray($request)
                : null,
        ];
    }
}
