<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Validates openapi components/schemas/DeviceAdminUpdate (PATCH). Only the keys present in the body
 * are applied; the JSON keys map 1:1 to device columns.
 */
class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof AdminUser;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'firmware_version' => ['sometimes', 'nullable', 'string', 'max:40'],
            'driver_type' => ['sometimes', 'string', 'in:traccar,ble,sms'],
            'sim_operator_id' => ['sometimes', 'nullable', 'integer', 'exists:sim_operators,id'],
            'sim_iccid' => ['sometimes', 'nullable', 'string', 'regex:/^\d{19,22}$/'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // GEOFENCE-1 — type-only here; the rule (enabling needs a positive radius + device coords)
            // is enforced by SetDeviceGeofence, so admin + owner share one implementation.
            'geofence_enabled' => ['sometimes', 'boolean'],
            'geofence_radius_m' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * Present, validated keys mapped 1:1 to device columns — EXCLUDING the geofence keys, which are
     * applied via SetDeviceGeofence (so its coord/radius rule runs) rather than mass-assigned.
     *
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        return Arr::except($this->validated(), ['geofence_enabled', 'geofence_radius_m']);
    }

    /** Whether this request changes any geofence setting (→ route it through SetDeviceGeofence). */
    public function touchesGeofence(): bool
    {
        $v = $this->validated();

        return array_key_exists('geofence_enabled', $v) || array_key_exists('geofence_radius_m', $v);
    }
}
