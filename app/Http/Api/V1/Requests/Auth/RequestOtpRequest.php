<?php

namespace App\Http\Api\V1\Requests\Auth;

use App\Domain\Auth\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi requestOtp body (phone + optional purpose). Public route. */
class RequestOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^\+994\d{9}$/'],
            'purpose' => ['sometimes', 'string', 'in:login,recover'],
        ];
    }

    public function purpose(): OtpPurpose
    {
        return OtpPurpose::from((string) $this->input('purpose', 'login'));
    }
}
