<?php

namespace App\Http\Resources;

use App\Domain\Devices\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 *
 * Maps to openapi components/schemas/Device — the per-caller view. `caller_role`,
 * `caller_can_open` and `caller_suspension_reason` are derived per request and attached to the model
 * by the controller (role from the roster row; access from the Subscriptions §13.9 read — R-DOM-07).
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'label' => $this->location_label ?? $this->serial,
            'serial' => $this->serial,
            'status' => $this->status->value,
            'role' => $this->caller_role,
            'can_open' => (bool) $this->caller_can_open,
            'suspension_reason' => $this->caller_suspension_reason,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            // GEOFENCE-1 — when true the app must attach the caller's GPS to /open (else the backend
            // rejects with location_required). The backend holds + enforces the radius.
            'geofence_enabled' => (bool) $this->geofence_enabled,
            'geofence_radius_m' => $this->geofence_radius_m,
            'last_online_at' => optional($this->last_online_at)->toIso8601String(),
            'image_url' => $this->image_url,
            'address' => $this->address,
        ];
    }
}
