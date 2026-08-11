<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Payments\Models\CardToken;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entitlement per (user, device) via device_user_id (R-DOM-03). Drives the open-permission check.
 */
class Subscription extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tier' => SubscriptionTier::class,
            'status' => SubscriptionStatus::class,
            'price_minor' => 'integer',
            'term_days' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
            'last_reminder_kind' => ReminderKind::class,
            'last_reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function deviceUser(): BelongsTo
    {
        return $this->belongsTo(DeviceUser::class, 'device_user_id');
    }

    public function cardToken(): BelongsTo
    {
        return $this->belongsTo(CardToken::class, 'card_token_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SubscriptionPeriod::class, 'subscription_id');
    }
}
