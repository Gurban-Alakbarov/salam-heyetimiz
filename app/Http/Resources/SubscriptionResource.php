<?php

namespace App\Http\Resources;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Subscription
 *
 * Maps to openapi components/schemas/Subscription (SubscriptionBrief + extra fields).
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'tier' => $this->tier->value,
            'status' => $this->status->value,
            'ends_at' => optional($this->ends_at)->toIso8601String(),
            'days_remaining' => $this->daysRemaining(),
            'auto_renew' => (bool) $this->auto_renew,
            'device_id' => optional($this->deviceUser)->device_id !== null ? (int) $this->deviceUser->device_id : null,
            'user_id' => optional($this->deviceUser)->user_id !== null ? (int) $this->deviceUser->user_id : null,
            'starts_at' => optional($this->starts_at)->toIso8601String(),
            'price_minor' => (int) $this->price_minor,
            'currency' => $this->currency,
            'term_days' => (int) $this->term_days,
            'last_reminder_kind' => $this->last_reminder_kind?->value,
            'last_reminder_sent_at' => optional($this->last_reminder_sent_at)->toIso8601String(),
        ];
    }

    private function daysRemaining(): ?int
    {
        if ($this->status !== SubscriptionStatus::Active || $this->ends_at === null) {
            return null;
        }

        $seconds = $this->ends_at->getTimestamp() - Carbon::now()->getTimestamp();

        return $seconds <= 0 ? 0 : (int) ceil($seconds / 86400);
    }
}
