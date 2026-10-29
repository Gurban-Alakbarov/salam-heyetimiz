<?php

namespace App\Http\Admin\V1\Requests\Devices;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Support\SubscriptionPriceResolver;
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

    /** A3 — null when not sent: the tier then follows the roster row (own access → main, family → additional). */
    public function tier(): ?SubscriptionTier
    {
        $tier = $this->validated()['tier'] ?? null;

        return $tier !== null ? SubscriptionTier::from((string) $tier) : null;
    }

    public function termDays(): int
    {
        return (int) ($this->validated()['term_days'] ?? app(SubscriptionPriceResolver::class)->commercial(SubscriptionTier::Main)['term_days']);
    }
}
