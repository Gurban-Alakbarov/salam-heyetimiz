<?php

namespace App\Http\Resources;

use App\Domain\DeviceComm\Models\DeviceDiagnostic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceDiagnostic
 *
 * Maps to openapi components/schemas/DeviceDiagnostic.
 */
class DeviceDiagnosticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'device_id' => (int) $this->device_id,
            'source' => $this->source->value,
            'online' => (bool) $this->online,
            'signal_strength' => $this->signal_strength !== null ? (int) $this->signal_strength : null,
            'battery_level' => $this->battery_level !== null ? (int) $this->battery_level : null,
            'firmware_version' => $this->firmware_version,
            'reported_at' => optional($this->reported_at)->toIso8601String(),
        ];
    }
}
