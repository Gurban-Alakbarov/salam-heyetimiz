<?php

namespace App\Http\Api\V1\Requests\Auth;

use App\Domain\Auth\DTOs\DeviceFingerprintData;
use App\Domain\Auth\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi verifyOtp body (phone, code, device fingerprint). Public route. */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'device' => ['required', 'array'],
            'device.install_uuid' => ['required', 'uuid'],
            'device.platform' => ['required', 'string', 'in:ios,android'],
            'device.os_version' => ['sometimes', 'nullable', 'string', 'max:40'],
            'device.app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
            'device.device_model' => ['sometimes', 'nullable', 'string', 'max:80'],
            'device.push_token' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function deviceData(): DeviceFingerprintData
    {
        $device = (array) $this->input('device');

        return new DeviceFingerprintData(
            installUuid: (string) $device['install_uuid'],
            platform: Platform::from((string) $device['platform']),
            osVersion: $device['os_version'] ?? null,
            appVersion: $device['app_version'] ?? null,
            deviceModel: $device['device_model'] ?? null,
            pushToken: $device['push_token'] ?? null,
        );
    }
}
