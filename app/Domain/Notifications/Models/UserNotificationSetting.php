<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user, per-(template, channel) mute preference (DB Arch §7.4). Only mutable-category templates
 * (marketing) may be disabled; security/billing are forced. Preferences UI is deferred (E6) — the
 * schema exists so the dispatcher can honour a mute once the UI ships.
 */
class UserNotificationSetting extends Model
{
    protected $table = 'user_notification_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
