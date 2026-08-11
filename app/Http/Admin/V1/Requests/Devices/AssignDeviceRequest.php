<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceAssignmentData;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the techAssignDevice / adminAssignDevice body (owner phone + optional placement). */
class AssignDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::DEVICES_ASSIGN);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owner_phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function toData(): DeviceAssignmentData
    {
        $v = $this->validated();

        return new DeviceAssignmentData(
            ownerPhone: (string) $v['owner_phone'],
            regionId: isset($v['region_id']) ? (int) $v['region_id'] : null,
            locationLabel: $v['location_label'] ?? null,
            latitude: isset($v['latitude']) ? (float) $v['latitude'] : null,
            longitude: isset($v['longitude']) ? (float) $v['longitude'] : null,
        );
    }
}
