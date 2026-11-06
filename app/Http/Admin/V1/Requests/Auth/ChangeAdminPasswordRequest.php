<?php

namespace App\Http\Admin\V1\Requests\Auth;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Settings\AdminPasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi adminChangePassword body. */
class ChangeAdminPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'different:current_password', ...app(AdminPasswordPolicy::class)->rules()],
        ];
    }
}
