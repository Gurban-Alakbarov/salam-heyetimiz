<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-locale (az/ru/en) subject + body for a notification template (DB Arch §7.2). */
class NotificationTemplateLocale extends Model
{
    protected $table = 'notification_template_locales';

    protected $guarded = ['id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
