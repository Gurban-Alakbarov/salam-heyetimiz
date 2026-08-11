<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceTransferData;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the adminTransferDevice body (new owner phone, reason, keep_existing_users). */
class TransferDeviceRequest extends FormRequest
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
            'new_owner_phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'reason' => ['required', 'string', 'max:255'],
            'keep_existing_users' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): DeviceTransferData
    {
        $v = $this->validated();

        return new DeviceTransferData(
            newOwnerPhone: (string) $v['new_owner_phone'],
            reason: (string) $v['reason'],
            keepExistingUsers: (bool) ($v['keep_existing_users'] ?? true),
        );
    }
}
