<?php

namespace App\Http\Admin\V1\Requests\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * K4 — making an admin a complex_manager (role change) needs a complex, exactly as on create: the target
     * row's current complex counts unless this request clears it. (Unbinding an existing manager is allowed —
     * it then sees no complex at all, AdminUser::NO_COMPLEX_SCOPE — never every complex.)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->input('role') !== AdminRole::ComplexManager->value) {
                return;
            }
            $target = AdminUser::query()->find((int) $this->route('adminId'));
            $complexId = $this->exists('complex_id') ? $this->input('complex_id') : $target?->complex_id;
            if ($complexId === null || $complexId === '') {
                $v->errors()->add('complex_id', 'complex_manager rolu üçün kompleks seçilməlidir.');
            }
        });
    }
}
