<?php

namespace App\Domain\Visitor\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Enums\VisitorPurpose;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A temporary open-grant for a barrier, shared with a visitor as a link. The secret token is never held
 * here — only `token_hash`. Validity is derived (not stored) from revoked_at / expires_at / usage_count
 * vs max_usage; the authoritative decision (with a reason) lives in VisitorAccessService.
 */
class VisitorLink extends Model
{
    use SoftDeletes;

    protected $table = 'visitor_links';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'access_type' => VisitorAccessType::class,
            'purpose' => VisitorPurpose::class,
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_usage' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    /**
     * Live links only: not revoked, not past expiry, not usage-exhausted. Mirrors the isLive() predicate in
     * SQL so the anti-abuse count and the admin "active" filter agree.
     *
     * @param  Builder<VisitorLink>  $query
     * @return Builder<VisitorLink>
     */
    public function scopeActive(Builder $query, CarbonInterface $now): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->where(fn (Builder $q) => $q->whereNull('max_usage')->orWhereColumn('usage_count', '<', 'max_usage'));
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VisitorLinkUsage::class, 'visitor_link_id');
    }

    // ---- Pure predicates (no side effects) ----

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(CarbonInterface $now): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($now);
    }

    public function isUsageExhausted(): bool
    {
        return $this->max_usage !== null && $this->usage_count >= $this->max_usage;
    }

    public function isLive(CarbonInterface $now): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired($now) && ! $this->isUsageExhausted();
    }

    /** Derived display status (no DB column): active | revoked | expired | used_up. */
    public function statusLabel(CarbonInterface $now): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired($now) => 'expired',
            $this->isUsageExhausted() => 'used_up',
            default => 'active',
        };
    }
}
