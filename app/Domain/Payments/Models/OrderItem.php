<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\OrderItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'item_type' => OrderItemType::class,
            'referenced_id' => 'integer',
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
