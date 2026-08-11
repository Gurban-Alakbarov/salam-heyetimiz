<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentLogDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, RANGE-partitioned (id, partition_key). Inserted only; never updated/deleted via
 * Eloquent, so the single-key 'id' assumption is safe. Encrypted columns carry allowlist-serialized
 * JSON written by PaymentLogger (HIGH-15).
 */
class PaymentLog extends Model
{
    public $timestamps = false;

    protected $table = 'payment_logs';

    protected $guarded = ['id'];

    protected $hidden = ['request_redacted_encrypted', 'response_redacted_encrypted'];

    protected function casts(): array
    {
        return [
            'partition_key' => 'integer',
            'direction' => PaymentLogDirection::class,
            'http_status' => 'integer',
            'request_redacted_encrypted' => 'encrypted',
            'response_redacted_encrypted' => 'encrypted',
            'signature_provided' => 'boolean',
            'signature_valid' => 'boolean',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
