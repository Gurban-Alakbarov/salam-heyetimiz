<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-editable notification template catalogue (DB Arch §7.1). Localized bodies live in
 * [NotificationTemplateLocale]. `default_channels_mask` selects channels (push=1/sms=2/inapp=4/email=8);
 * `category` decides whether a user may mute it (marketing only).
 */
class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'default_channels_mask' => 'integer',
            'category' => NotificationCategory::class,
            'is_user_mutable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function locales(): HasMany
    {
        return $this->hasMany(NotificationTemplateLocale::class, 'notification_template_id');
    }
}
