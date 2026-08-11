<?php

namespace App\Http\Resources;

use App\Domain\Payments\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 *
 * Maps to openapi components/schemas/OrderItem.
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'item_type' => $this->item_type->value,
            'referenced_id' => $this->referenced_id !== null ? (int) $this->referenced_id : null,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'unit_amount_minor' => (int) $this->unit_amount_minor,
            'total_amount_minor' => (int) $this->total_amount_minor,
        ];
    }
}
