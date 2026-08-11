<?php

namespace App\Http\Admin\V1\Requests\Admins;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/** Validates adminCreateAdmin. The admins.create permission is checked here (403 precedes body validation). */
class CreateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::ADMINS_CREATE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:160', 'unique:admin_users,email'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'role' => ['required', 'string', 'in:super_admin,technical,operator,finance,support,complex_manager'],
            'complex_id' => ['nullable', 'integer', 'exists:complexes,id', 'required_if:role,complex_manager'],
        ];
    }
}
