<?php

namespace App\Http\Resources;

use App\Domain\Devices\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 *
 * Maps to openapi components/schemas/DeviceAdmin. `whitelist_capacity_used` is computed on read
 * (R-DOM-14 — the column is deprecated) from the active roster count, attached by the controller.
 */
class DeviceAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'serial' => $this->serial,
            'sim_phone' => $this->sim_phone,
            'sim_operator' => $this->relationLoaded('simOperator') && $this->simOperator !== null
                ? (new SimOperatorResource($this->simOperator))->toArray($request)
                : null,
            'device_model' => $this->relationLoaded('model') && $this->model !== null
                ? (new DeviceModelResource($this->model))->toArray($request)
                : null,
            'driver_type' => $this->driver_type->value,
            'status' => $this->status->value,
            'owner' => $this->relationLoaded('owner') && $this->owner !== null
                ? (new UserBriefResource($this->owner))->toArray($request)
                : null,
            'region' => $this->relationLoaded('region') && $this->region !== null
                ? (new RegionResource($this->region))->toArray($request)
                : null,
            'location_label' => $this->location_label,
            'image_url' => $this->image_url,
            'address' => $this->address,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'last_online_at' => optional($this->last_online_at)->toIso8601String(),
            'online' => $this->isOnline(),
            'last_signal_strength' => $this->last_signal_strength !== null ? (int) $this->last_signal_strength : null,
            'whitelist_capacity_used' => (int) ($this->whitelist_used ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }

    /** Real connectivity: telemetry seen within the offline window (last_online_at is webhook-fed from Traccar). */
    private function isOnline(): bool
    {
        if ($this->last_online_at === null) {
            return false;
        }

        $minutes = (int) config('domain.devices.offline_threshold_minutes', 15);

        return $this->last_online_at->greaterThan(now()->subMinutes($minutes));
    }
}
