<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the adminDisableDevice body (reason). */
class DisableDeviceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
