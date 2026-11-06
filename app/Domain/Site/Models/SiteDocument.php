<?php

namespace App\Domain\Site\Models;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One editable piece of the website: a page, or "site" for the texts shared by all pages. */
class SiteDocument extends Model
{
    public const SHARED = 'site';

    protected $table = 'site_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'version' => 'integer',
            'published_version' => 'integer',
        ];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SiteRevision::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->published_version !== $this->version;
    }
}
