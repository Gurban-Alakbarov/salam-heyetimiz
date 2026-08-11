<?php

namespace App\Http\Resources;

use App\Domain\DeviceComm\Models\WhitelistChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhitelistChange
 *
 * Maps to openapi components/schemas/WhitelistChange.
 */
class WhitelistChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'device_id' => (int) $this->device_id,
            'action' => $this->action->value,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'attempt_count' => (int) $this->attempt_count,
            'last_error' => $this->last_error,
            'last_attempt_at' => optional($this->last_attempt_at)->toIso8601String(),
            'next_attempt_at' => optional($this->next_attempt_at)->toIso8601String(),
            'synced_at' => optional($this->synced_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
