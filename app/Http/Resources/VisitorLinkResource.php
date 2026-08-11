<?php

namespace App\Http\Resources;

use App\Domain\Visitor\Models\VisitorLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VisitorLink
 *
 * A visitor link as its owner (resident or admin) sees it. Never includes the token — it is
 * unrecoverable (only the hash is stored) and is returned in the clear exactly once, at creation.
 */
class VisitorLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => (int) $this->id,
            'visitor_name' => $this->visitor_name,
            'purpose' => $this->purpose?->value,
            'access_type' => $this->access_type->value,
            'status' => $this->statusLabel($now),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'max_usage' => $this->max_usage,
            'usage_count' => (int) $this->usage_count,
            'last_used_at' => optional($this->last_used_at)->toIso8601String(),
            'revoked_at' => optional($this->revoked_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
