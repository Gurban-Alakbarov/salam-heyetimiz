<?php

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * B7 risk "abandoned intent → zombie roster": a complex resident who started a subscribe but never paid
 * leaves an active roster row + a never-paid pending subscription. After `abandoned_intent_hours` with no
 * in-flight order, the subscription is cancelled (reason abandoned_intent) and the self-created roster row
 * revoked. Rows with ANY paid history, family rows (B8) and private devices are never touched. A later
 * subscribe simply reactivates the row and reopens the subscription (B2/B4).
 */
final class SweepAbandonedSubscriptionIntents
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly RosterService $roster,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    public function handle(): int
    {
        $cutoff = $this->clock->now()->subHours((int) config('domain.subscriptions.abandoned_intent_hours', 24));
        $count = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::PendingPayment->value)
            ->where('updated_at', '<=', $cutoff)
            ->whereDoesntHave('periods')
            ->whereHas('deviceUser', fn ($q) => $q
                ->where('status', DeviceUserStatus::Active->value)
                ->whereNull('family_link_id')
                ->whereColumn('added_by_user_id', 'device_users.user_id')
                ->whereHas('device', fn ($d) => $d->where('ownership_mode', DeviceOwnershipMode::Complex->value)))
            ->with('deviceUser.device', 'deviceUser.user')
            ->chunkById(200, function ($rows) use (&$count): void {
                foreach ($rows as $subscription) {
                    if ($this->hasInFlightOrder((int) $subscription->getKey())) {
                        continue;
                    }
                    /** @var DeviceUser $row */
                    $row = $subscription->deviceUser;

                    DB::transaction(function () use ($subscription, $row): void {
                        $this->subscriptions->cancel($subscription, 'abandoned_intent');
                        $this->roster->removeMember($row->device, $row->user, ActorKind::System, null);
                        $this->audit->record('subscription.abandoned_intent_swept', [
                            'subscription_id' => (int) $subscription->getKey(),
                            'device_user_id' => (int) $row->getKey(),
                            'device_id' => (int) $row->device_id,
                            'user_id' => (int) $row->user_id,
                        ], Subscription::class, (int) $subscription->getKey());
                    });
                    $count++;
                }
            });

        return $count;
    }

    private function hasInFlightOrder(int $subscriptionId): bool
    {
        return Order::query()
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Authorising->value])
            ->whereHas('items', fn ($q) => $q->whereIn('item_type', [OrderItemType::SubMain->value, OrderItemType::SubAdditional->value, OrderItemType::SubRenewal->value])
                ->where('referenced_id', $subscriptionId))
            ->exists();
    }
}
