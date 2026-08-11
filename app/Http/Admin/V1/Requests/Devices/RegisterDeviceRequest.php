<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Enums\DriverType;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi components/schemas/DeviceTechRegister (techRegisterDevice / adminCreateDevice). */
class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::DEVICES_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'serial' => ['required', 'string', 'min:4', 'max:64', 'unique:devices,serial'],
            'device_model_id' => ['required', 'integer', 'exists:device_models,id'],
            'sim_phone' => ['required', 'string', 'regex:/^\+994\d{9}$/', 'unique:devices,sim_phone'],
            'sim_operator_id' => ['sometimes', 'nullable', 'integer', 'exists:sim_operators,id'],
            'sim_iccid' => ['sometimes', 'nullable', 'string', 'regex:/^\d{19,22}$/'],
            'driver_type' => ['required', 'string', 'in:traccar,ble,sms'],
            'firmware_version' => ['sometimes', 'nullable', 'string', 'max:40'],
            'region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'location_label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url', 'max:2048'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function toData(): DeviceRegistrationData
    {
        $v = $this->validated();

        return new DeviceRegistrationData(
            serial: (string) $v['serial'],
            deviceModelId: (int) $v['device_model_id'],
            simPhone: (string) $v['sim_phone'],
            driverType: DriverType::from((string) $v['driver_type']),
            simOperatorId: isset($v['sim_operator_id']) ? (int) $v['sim_operator_id'] : null,
            simIccid: $v['sim_iccid'] ?? null,
            firmwareVersion: $v['firmware_version'] ?? null,
            regionId: isset($v['region_id']) ? (int) $v['region_id'] : null,
            locationLabel: $v['location_label'] ?? null,
            imageUrl: $v['image_url'] ?? null,
            address: $v['address'] ?? null,
            latitude: isset($v['latitude']) ? (float) $v['latitude'] : null,
            longitude: isset($v['longitude']) ? (float) $v['longitude'] : null,
            metadata: $v['metadata'] ?? null,
        );
    }
}
