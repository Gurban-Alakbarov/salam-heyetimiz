<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Enums\SubscriptionPeriodKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPeriod extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'subscription_periods';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => SubscriptionPeriodKind::class,
            'amount_minor' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
