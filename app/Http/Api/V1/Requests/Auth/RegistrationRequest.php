<?php

namespace App\Http\Api\V1\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base for the registration/email-auth requests. Overrides failedValidation so validation errors come
 * back in the SAME unified envelope (`{ success:false, message, errors }`) the endpoints use for
 * success — instead of the legacy `{error}` renderer. Public routes (no auth).
 */
abstract class RegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Doğrulama xətası baş verdi.',
            'data' => null,
            'meta' => null,
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
