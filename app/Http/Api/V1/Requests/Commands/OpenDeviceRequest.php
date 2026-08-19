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
            // GEOFENCE-1 — the caller's ephemeral GPS fix. Optional at the transport level; the open
            // action REQUIRES it only when the target device has geofencing enabled (else it is
            // ignored). Never stored as location history — used once to compute distance server-side.
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
