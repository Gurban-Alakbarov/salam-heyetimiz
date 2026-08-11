<?php

namespace App\Http\Api\V1\Requests\Commands;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the openDevice body (optional client_app_version). Idempotency-Key checked in the controller. */
class OpenDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
            // Phase 5: optional relay direction through the SAME open pipeline. Omitted → open
            // (the historical behaviour is unchanged). 'close' issues a close command that reads the
            // device model's close_command (VL110C RELAY,0#).
            'direction' => ['sometimes', 'nullable', 'string', 'in:open,close'],
        ];
    }
}
