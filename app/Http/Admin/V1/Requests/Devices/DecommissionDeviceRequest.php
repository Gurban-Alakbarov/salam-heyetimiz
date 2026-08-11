<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the adminDecommissionDevice body (reason, 3–255). */
class DecommissionDeviceRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
