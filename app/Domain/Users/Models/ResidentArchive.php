<?php

namespace App\Domain\Users\Models;

use App\Domain\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O10 — the personal data + a snapshot of an account an admin deleted from the system, kept until purge_after
 * (privacy.resident_archive_years). Read-only after it is written; viewable with residents.archive.view.
 *
 * @property int $id
 * @property int $user_id
 * @property ?string $phone
 * @property ?string $email
 * @property ?string $full_name
 * @property ?int $deleted_by_admin_id
 * @property string $reason
 * @property ?array<string, mixed> $snapshot
 * @property \Illuminate\Support\Carbon $archived_at
 * @property \Illuminate\Support\Carbon $purge_after
 */
class ResidentArchive extends Model
{
    public const REASON_ADMIN_DELETED = 'admin_deleted';

    /** Deleted by an admin before deletions anonymised (found by `users:anonymise-deleted`). */
    public const REASON_LEGACY_DELETED = 'legacy_deleted';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'email_verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_login_at' => 'datetime',
            'archived_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    /** @return BelongsTo<AdminUser, $this> */
    public function deletedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'deleted_by_admin_id');
    }
}
