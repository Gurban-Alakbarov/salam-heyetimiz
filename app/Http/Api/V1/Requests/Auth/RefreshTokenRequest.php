<?php

namespace App\Http\Api\V1\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi refreshToken body. Public route. */
class RefreshTokenRequest extends FormRequest
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
            'refresh_token' => ['required', 'string', 'min:30', 'max:200'],
        ];
    }
}
