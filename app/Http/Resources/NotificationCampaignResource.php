<?php

namespace App\Http\Resources;

use App\Domain\Notifications\Models\NotificationCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationCampaign
 *
 * Maps to openapi components/schemas/NotificationCampaign. A campaign carries NO channels_mask — channel
 * selection is template-driven (R-NOT-04), so none is emitted.
 */
class NotificationCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'language' => $this->language,
            'audience_scope' => $this->audience_scope->value,
            'status' => $this->status->value,
            'total_recipients' => $this->total_recipients !== null ? (int) $this->total_recipients : null,
            'sent_count' => (int) $this->sent_count,
            'failed_count' => (int) $this->failed_count,
            'created_by_admin_id' => (int) $this->created_by_admin_id,
            'confirmed_at' => optional($this->confirmed_at)->toIso8601String(),
            'sent_at' => optional($this->sent_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
