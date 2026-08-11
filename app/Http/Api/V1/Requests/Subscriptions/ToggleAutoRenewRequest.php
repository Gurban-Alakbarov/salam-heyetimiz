<?php

namespace App\Http\Api\V1\Requests\Subscriptions;

use App\Domain\Subscriptions\DTOs\ToggleAutoRenewData;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the toggleAutoRenew body. */
class ToggleAutoRenewRequest extends FormRequest
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
            'auto_renew' => ['required', 'boolean'],
            'card_token_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function toData(): ToggleAutoRenewData
    {
        $validated = $this->validated();

        return new ToggleAutoRenewData(
            autoRenew: (bool) $validated['auto_renew'],
            cardTokenId: isset($validated['card_token_id']) ? (int) $validated['card_token_id'] : null,
        );
    }
}
