<?php

namespace App\Http\Admin\V1\Requests\Subscriptions;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Subscriptions\Enums\SubscriptionPriceScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A special subscription price for one person, device or complex. 0 AZN is refused: "Abunəlik ver" is the free way. */
class SetSubscriptionPriceRequest extends FormRequest
{
    private const SUBJECT_TABLES = [
        'user' => 'users',
        'device' => 'devices',
        'complex' => 'complexes',
    ];

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
        $table = self::SUBJECT_TABLES[(string) $this->input('scope')] ?? null;

        return [
            'scope' => ['required', Rule::enum(SubscriptionPriceScope::class)],
            'subject_id' => ['required', 'integer', ...($table !== null ? [Rule::exists($table, 'id')] : [])],
            'price_minor' => ['required', 'integer', 'min:100', 'max:100000'],
        ];
    }
}
