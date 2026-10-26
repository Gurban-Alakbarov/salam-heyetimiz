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
            // A4 — the caller's access comes through a family head (BR-19: may open, never share), and whether the
            // caller may create visitor links on this device at all (= can open AND not via family).
            'via_family' => (bool) $this->caller_via_family,
            'can_share_visitor_link' => (bool) $this->caller_can_open && ! $this->caller_via_family,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            // GEOFENCE-1 — when true the app must attach the caller's GPS to /open (else the backend
            // rejects with location_required). The backend holds + enforces the radius.
            'geofence_enabled' => (bool) $this->geofence_enabled,
            'geofence_radius_m' => $this->geofence_radius_m,
            'last_online_at' => optional($this->last_online_at)->toIso8601String(),
            // A2 — computed with the shared window (domain.devices.offline_threshold_minutes) so the app shows the
            // same online state as the admin panel instead of using its own threshold.
            'online' => $this->last_online_at !== null
                && $this->last_online_at->greaterThan(now()->subMinutes((int) config('domain.devices.offline_threshold_minutes', 15))),
            'image_url' => $this->image_url,
            'address' => $this->address,
        ];
    }
}
