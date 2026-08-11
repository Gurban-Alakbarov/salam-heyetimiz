<?php

namespace App\Http\Api\V1\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi upsertPushToken body. Guarded route (auth:user). */
class UpsertPushTokenRequest extends FormRequest
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
            'push_token' => ['required', 'string', 'min:10', 'max:255'],
        ];
    }
}
