<?php

namespace App\Http\Admin\V1\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi adminLogin body (email + password). Public route. */
class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ];
    }
}
