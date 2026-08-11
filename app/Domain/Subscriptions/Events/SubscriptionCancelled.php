<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionCancelled implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'subscription.cancelled';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'reason' => $this->reason,
        ];
    }
}
