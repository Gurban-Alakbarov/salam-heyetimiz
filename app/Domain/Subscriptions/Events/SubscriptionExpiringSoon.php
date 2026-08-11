<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the daily reminder sweep at the D-30/15/7/1 thresholds (Tech Spec §13.4). Notifications
 * (batch 08) maps the kind to push/in-app/SMS templates.
 */
class SubscriptionExpiringSoon implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ReminderKind $kind,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.expiring_soon';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'kind' => $this->kind->value,
            'ends_at' => optional($this->subscription->ends_at)->toIso8601String(),
        ];
    }
}
