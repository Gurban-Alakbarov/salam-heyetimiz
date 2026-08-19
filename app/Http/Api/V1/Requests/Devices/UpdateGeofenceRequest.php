<?php

namespace App\Http\Api\V1\Requests\Devices;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the owner geofence-config body (GEOFENCE-1 D5). Type-only here; the domain rule (enabling
 * requires a positive radius + the device to already have coordinates) lives in SetDeviceGeofence. The
 * owner may ONLY toggle geofencing and set its radius — this request exposes no coordinate, ownership,
 * roster or subscription field, so none of those can change through it.
 */
class UpdateGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'geofence_enabled' => ['required', 'boolean'],
            'geofence_radius_m' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
