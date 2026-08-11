<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentCallbackOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCallback extends Model
{
    public $timestamps = false;

    protected $table = 'payment_callbacks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'outcome' => PaymentCallbackOutcome::class,
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
