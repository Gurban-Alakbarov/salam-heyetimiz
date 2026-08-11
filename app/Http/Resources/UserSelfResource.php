<?php

namespace App\Http\Resources;

use App\Domain\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * Maps to openapi components/schemas/UserSelf. `has_active_subscription` is set on the
 * model by the caller (resolved via the Subscriptions read model — R-ARCH-06).
 */
class UserSelfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'phone' => $this->phone,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'email_verified_at' => optional($this->email_verified_at)->toIso8601String(),
            'preferred_language' => $this->preferred_language->value,
            'status' => $this->status->value,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'last_login_at' => optional($this->last_login_at)->toIso8601String(),
            'has_active_subscription' => (bool) ($this->has_active_subscription ?? false),
        ];
    }
}
