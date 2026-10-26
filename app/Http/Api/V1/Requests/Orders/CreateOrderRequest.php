<?php

namespace App\Http\Api\V1\Requests\Orders;

use App\Domain\Payments\DTOs\OrderCreationData;
use App\Domain\Payments\DTOs\OrderItemData;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Enums\OrderPurpose;
use App\Domain\Payments\Models\Order;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Validates openapi components/schemas/OrderCreate. */
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->can('create', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'in:device_sale,sub_main,sub_additional,sub_renewal,bundle'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.item_type' => ['required', 'string', 'in:device,sub_main,sub_additional,sub_renewal'],
            'items.*.referenced_id' => ['required', 'integer', 'min:1'],
            // O3 — every line is ONE thing (a device, or one subscription period); a quantity of 10 used to charge
            // 10x for a single period. Optional; 1 is the only accepted value.
            'items.*.quantity' => ['sometimes', 'integer', 'in:1'],
            'auto_renew' => ['sometimes', 'boolean'],
            'return_url' => ['sometimes', 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && ! self::isAllowedReturnUrl((string) $value)) {
                    $fail('return_url yalnız tətbiqə (salam://) və ya icazə verilmiş https ünvana yönləndirə bilər.');
                }
            }],
        ];
    }

    /** O3 — back into the app (the configured salam:// scheme) or an https page on an allow-listed host. */
    public static function isAllowedReturnUrl(string $url): bool
    {
        $appScheme = (string) parse_url((string) config('integrations.kapital.return_url_scheme'), PHP_URL_SCHEME);
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($appScheme !== '' && $scheme === strtolower($appScheme)) {
            return true;
        }

        return $scheme === 'https'
            && isset($parts['host'])
            && ! isset($parts['user'])
            && in_array(strtolower($parts['host']), array_map('strtolower', (array) config('integrations.kapital.allowed_return_hosts')), true);
    }

    public function toData(): OrderCreationData
    {
        $validated = $this->validated();

        $items = array_map(
            static fn (array $item): OrderItemData => new OrderItemData(
                itemType: OrderItemType::from($item['item_type']),
                referencedId: (int) $item['referenced_id'],
                quantity: (int) ($item['quantity'] ?? 1),
            ),
            $validated['items'],
        );

        return new OrderCreationData(
            purpose: OrderPurpose::from($validated['purpose']),
            items: $items,
            autoRenew: (bool) ($validated['auto_renew'] ?? false),
            returnUrl: $validated['return_url'] ?? null,
        );
    }
}
