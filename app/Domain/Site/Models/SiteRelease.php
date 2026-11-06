<?php

namespace App\Domain\Site\Models;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A built copy of the website on disk. Written once; the live site points at one of them. */
class SiteRelease extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'site_releases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'versions' => 'array',
            'pages' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_id');
    }
}
