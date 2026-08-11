<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-(user × channel) notification record (DB Arch §7.3). Drives the in-app inbox and records the
 * push/sms/inapp/email delivery outcome. Partitioned monthly by `partition_key`; it has `created_at`
 * only (no `updated_at`). The `user_id` / `campaign_id` relations are app-enforced — the partitioned
 * table carries no DB foreign keys (documented platform limitation, see the migration).
 *
 * `payload` holds the rendered `title`, `body`, `type` and deep-link `ids` (R-NOT-03/17) — never
 * secrets/tokens; it is both the inbox source and the source for the FCM message.
 */
class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notifications';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'partition_key' => 'integer',
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }
}
