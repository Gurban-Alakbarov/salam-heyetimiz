<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a manual (unpaid) subscription grant for a roster member (adminGrantSubscription).
 * Money is NOT involved — Payments owns orders/refunds; this only grants entitlement time.
 */
class GrantSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof AdminUser && $actor->hasPermission(Permission::SUBSCRIPTIONS_MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tier' => ['sometimes', 'string', 'in:main,additional'],
            'term_days' => ['sometimes', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function tier(): SubscriptionTier
    {
        return SubscriptionTier::from((string) ($this->validated()['tier'] ?? SubscriptionTier::Additional->value));
    }

    public function termDays(): int
    {
        return (int) ($this->validated()['term_days'] ?? config('domain.subscriptions.term_days', 365));
    }
}
