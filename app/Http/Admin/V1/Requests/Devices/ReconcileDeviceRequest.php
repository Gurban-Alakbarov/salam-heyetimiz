<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;

/**
 * Validates a device reconciliation (adminReconcileDevice): the same DeviceTechRegister fields as a
 * normal registration, plus the Traccar `unique_id` (IMEI) of the existing Traccar device to adopt.
 */
class ReconcileDeviceRequest extends RegisterDeviceRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::DEVICES_RECONCILE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'unique_id' => ['required', 'string', 'min:6', 'max:128'],
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->validated()['unique_id'];
    }
}
