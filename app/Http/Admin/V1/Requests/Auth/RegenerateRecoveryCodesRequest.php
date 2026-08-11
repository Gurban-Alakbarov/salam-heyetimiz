<?php

namespace App\Http\Admin\V1\Requests\Auth;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi regenerateRecoveryCodes body (fresh TOTP re-auth). */
class RegenerateRecoveryCodesRequest extends FormRequest
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
            'totp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
