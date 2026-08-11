<?php

namespace App\Http\Resources;

use App\Domain\DeviceComm\Models\OpenCommand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OpenCommand
 *
 * Maps to openapi components/schemas/OpenCommand.
 */
class OpenCommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'device_id' => (int) $this->device_id,
            'state' => $this->state->value,
            'direction' => $this->direction?->value,
            'source' => $this->source->value,
            'failure_reason' => $this->failure_reason,
            'driver' => $this->driver->value,
            'command_text' => data_get($this->metadata, 'command'),
            'terminal_response' => data_get($this->metadata, 'response'),
            'requested_at' => optional($this->requested_at)->toIso8601String(),
            'dispatched_at' => optional($this->dispatched_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'latency_ms' => $this->latency_ms !== null ? (int) $this->latency_ms : null,
            'attempts' => (int) $this->attempts,
        ];
    }
}
