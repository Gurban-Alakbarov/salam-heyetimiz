<?php

namespace App\Domain\Visitor\Models;

use App\Domain\Visitor\Enums\VisitorUsageResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One audited visitor open attempt (append-only). */
class VisitorLinkUsage extends Model
{
    public const UPDATED_AT = null; // append-only: created_at only

    protected $table = 'visitor_link_usages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'result' => VisitorUsageResult::class,
            'used_at' => 'datetime',
            'open_command_id' => 'integer',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(VisitorLink::class, 'visitor_link_id');
    }
}
