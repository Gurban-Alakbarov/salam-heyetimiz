<?php

namespace App\Http\Api\V1\Requests\Auth;

/** Validates POST /v1/auth/register (format only — existence/reuse logic lives in RegisterUser). */
class RegisterRequest extends RegistrationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:1', 'max:60'],
            'last_name' => ['required', 'string', 'min:1', 'max:60'],
            'phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
            'invitation_token' => ['sometimes', 'nullable', 'string', 'max:128'], // B6 — optional invite claim
            'account_type' => ['sometimes', 'nullable', 'string', 'in:physical,legal'], // B9 — optional; NULL = legacy
        ];
    }
}
