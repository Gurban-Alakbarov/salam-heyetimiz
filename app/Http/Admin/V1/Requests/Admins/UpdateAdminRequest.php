<?php

namespace App\Http\Admin\V1\Requests\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/** Validates adminUpdateAdmin. admins.update is checked here; admins.assign_roles (role change) in the controller. */
class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::ADMINS_UPDATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'role' => ['sometimes', 'string', 'in:super_admin,technical,operator,finance,support,complex_manager'],
            'complex_id' => ['sometimes', 'nullable', 'integer', 'exists:complexes,id'],
            'status' => ['sometimes', 'string', 'in:active,suspended,offboarded'],
        ];
    }
}
