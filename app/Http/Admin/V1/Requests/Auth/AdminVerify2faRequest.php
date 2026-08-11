<?php

namespace App\Http\Admin\V1\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates openapi adminVerify2fa body: challenge_token + exactly one of totp / recovery_code
 * (the oneOf constraint). Public route.
 */
class AdminVerify2faRequest extends FormRequest
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
            'challenge_token' => ['required', 'string'],
            'totp' => ['required_without:recovery_code', 'prohibits:recovery_code', 'string', 'regex:/^\d{6}$/'],
            'recovery_code' => ['required_without:totp', 'string', 'regex:/^[0-9a-fA-F]{10}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('totp') && $this->filled('recovery_code')) {
                $validator->errors()->add('totp', 'Provide either a TOTP code or a recovery code, not both.');
            }
        });
    }

    public function totp(): ?string
    {
        $value = $this->input('totp');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function recoveryCode(): ?string
    {
        $value = $this->input('recovery_code');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
