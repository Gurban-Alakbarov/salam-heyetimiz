<?php

namespace App\Http\Resources;

use App\Domain\Notifications\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 *
 * Maps to openapi components/schemas/Notification (in-app inbox item). `payload` carries the rendered
 * title/body + the deep-link `type`/`ids` (R-NOT-03); never secrets/tokens (R-NOT-17).
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'template_key' => $this->template_key,
            'channel' => $this->channel->value,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'sent_at' => optional($this->sent_at)->toIso8601String(),
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
