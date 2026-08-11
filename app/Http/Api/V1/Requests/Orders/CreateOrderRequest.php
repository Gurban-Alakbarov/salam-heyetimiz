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
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'auto_renew' => ['sometimes', 'boolean'],
            'return_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ];
    }

    public function toData(): OrderCreationData
    {
        $validated = $this->validated();

        $items = array_map(
            static fn (array $item): OrderItemData => new OrderItemData(
                itemType: OrderItemType::from($item['item_type']),
                referencedId: (int) $item['referenced_id'],
                quantity: (int) $item['quantity'],
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
