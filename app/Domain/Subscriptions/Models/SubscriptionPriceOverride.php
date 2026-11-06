<?php

namespace App\Domain\Subscriptions\Models;

use App\Domain\Subscriptions\Enums\SubscriptionPriceScope;
use Illuminate\Database\Eloquent\Model;

/** A special subscription price for one person, device or complex (one row per subject). */
class SubscriptionPriceOverride extends Model
{
    protected $table = 'subscription_price_overrides';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scope' => SubscriptionPriceScope::class,
            'subject_id' => 'integer',
            'price_minor' => 'integer',
        ];
    }
}
