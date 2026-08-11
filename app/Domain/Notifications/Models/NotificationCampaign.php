<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Notifications\Enums\CampaignAudienceScope;
use App\Domain\Notifications\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One admin-initiated notification send (DB Arch §7.5; Tech Spec §17.6). Delivery is always the
 * fixed push + inapp channels — a campaign carries NO channel mask (R-NOT-04). Fan-out writes one
 * per-channel [Notification] row per recipient, linked by `campaign_id`.
 */
class NotificationCampaign extends Model
{
    protected $table = 'notification_campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'audience_scope' => CampaignAudienceScope::class,
            'audience_filter' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'confirmed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'campaign_id');
    }
}
