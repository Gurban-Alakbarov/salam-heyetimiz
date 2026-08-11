<?php

namespace App\Http\Resources;

use App\Domain\Visitor\Models\VisitorLinkUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VisitorLinkUsage
 *
 * One audited visitor open attempt (admin usage log): when, the outcome, and the client that tried. Scoped
 * to a single link by the endpoint, so no link id is echoed back.
 */
class VisitorLinkUsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'used_at' => optional($this->used_at)->toIso8601String(),
            'result' => $this->result->value,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
        ];
    }
}
