<?php

namespace App\Domain\Site\Models;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved version of a website document. Written once, never changed. */
class SiteRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'site_revisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'version' => 'integer',
            'restored_from' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }
}
