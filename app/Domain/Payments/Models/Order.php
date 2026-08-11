<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\OrderPurpose;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'orders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'purpose' => OrderPurpose::class,
            'status' => OrderStatus::class,
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function callbacks(): HasMany
    {
        return $this->hasMany(PaymentCallback::class, 'order_id');
    }

    /** Net captured amount (gross charges minus refunds), in minor units. */
    public function netCapturedMinor(): int
    {
        return (int) $this->payments()
            ->where('status', 'approved')
            ->whereIn('type', ['charge', 'refund'])
            ->sum('amount_minor');
    }
}
