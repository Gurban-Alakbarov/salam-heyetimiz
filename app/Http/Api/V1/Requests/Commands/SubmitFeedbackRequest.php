<?php

namespace App\Http\Api\V1\Requests\Commands;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the submitOpenFeedback body (gate_moved required; optional comment). */
class SubmitFeedbackRequest extends FormRequest
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
            'gate_moved' => ['required', 'boolean'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
