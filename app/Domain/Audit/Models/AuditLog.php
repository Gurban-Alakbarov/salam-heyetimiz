<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;

/** An append-only administrative audit entry. Never updated; created_at is the only timestamp. */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
