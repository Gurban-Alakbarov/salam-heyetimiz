<?php

namespace App\Http\Api\V1\Requests\Auth;

/** Validates POST /v1/auth/login (request a login OTP by email). */
class EmailLoginRequest extends RegistrationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:160'],
        ];
    }
}
