<?php

namespace App\Domain\Subscriptions\Events;

use App\Domain\Subscriptions\Models\Subscription;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionRenewed implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Subscription $subscription) {}

    public function auditAction(): string
    {
        return 'subscription.renewed';
    }

    public function auditPayload(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'ends_at' => optional($this->subscription->ends_at)->toIso8601String(),
        ];
    }
}
