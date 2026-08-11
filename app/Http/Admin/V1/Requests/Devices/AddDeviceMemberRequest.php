<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Roster\DTOs\RosterMemberData;
use App\Domain\Roster\Enums\DeviceUserRole;
use Illuminate\Foundation\Http\FormRequest;

/** Validates adding a resident to a device roster (adminAddDeviceUser). Owners are bound via /assign. */
class AddDeviceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::RESIDENTS_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'role' => ['sometimes', 'string', 'in:user'],
        ];
    }

    public function toData(): RosterMemberData
    {
        $v = $this->validated();

        return new RosterMemberData(
            phone: (string) $v['phone'],
            role: DeviceUserRole::User,
        );
    }
}
