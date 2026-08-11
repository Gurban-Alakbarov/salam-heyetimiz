<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payments';

    protected $guarded = ['id'];

    protected $hidden = ['raw_response_encrypted'];

    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'raw_response_encrypted' => 'encrypted',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function cardToken(): BelongsTo
    {
        return $this->belongsTo(CardToken::class, 'card_token_id');
    }
}
