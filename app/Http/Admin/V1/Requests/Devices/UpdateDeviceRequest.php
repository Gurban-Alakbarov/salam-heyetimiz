<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    /**
     * Present, validated keys mapped to device columns (names match 1:1).
     *
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        return $this->validated();
    }
}
