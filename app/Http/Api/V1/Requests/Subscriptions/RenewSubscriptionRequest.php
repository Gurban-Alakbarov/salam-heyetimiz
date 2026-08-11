<?php

namespace App\Http\Api\V1\Requests\Subscriptions;

use App\Domain\Subscriptions\DTOs\RenewSubscriptionData;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the renewSubscription body (optional return_url). */
class RenewSubscriptionRequest extends FormRequest
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
            'return_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function toData(): RenewSubscriptionData
    {
        return new RenewSubscriptionData(returnUrl: $this->input('return_url'));
    }
}
