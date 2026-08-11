<?php

namespace App\Domain\Payments\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Payments\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $table = 'refunds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'requested_by_admin_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'processed_by_admin_id');
    }

    public function linkedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'linked_payment_id');
    }
}
