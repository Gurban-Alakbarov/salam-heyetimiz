<?php

namespace App\Http\Api\V1\Requests\Visitor;

use App\Domain\Visitor\DTOs\CreateVisitorLinkData;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Enums\VisitorPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared visitor-link creation payload (mobile resident + admin). Input validation only — the caller's
 * authority (resident canOpen / admin permission) is enforced by each controller, so there is one set of
 * rules and no duplicated logic.
 */
class CreateVisitorLinkRequest extends FormRequest
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
            'visitor_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'purpose' => ['sometimes', 'nullable', Rule::enum(VisitorPurpose::class)],
            'access_type' => ['required', Rule::enum(VisitorAccessType::class)],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:'.(int) config('domain.visitor.min_duration_minutes'),
                'max:'.(int) config('domain.visitor.max_duration_minutes'),
                Rule::requiredIf(fn () => $this->input('access_type') === VisitorAccessType::TimeLimited->value),
            ],
        ];
    }

    public function toData(): CreateVisitorLinkData
    {
        return new CreateVisitorLinkData(
            accessType: VisitorAccessType::from((string) $this->validated('access_type')),
            visitorName: $this->validated('visitor_name'),
            durationMinutes: $this->validated('duration_minutes') !== null ? (int) $this->validated('duration_minutes') : null,
            purpose: $this->validated('purpose') !== null ? VisitorPurpose::from((string) $this->validated('purpose')) : null,
        );
    }
}
