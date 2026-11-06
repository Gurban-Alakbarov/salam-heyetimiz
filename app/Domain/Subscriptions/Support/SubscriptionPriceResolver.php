<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Enums\OrderItemType;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionPriceScope;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Models\SubscriptionPriceOverride;

/**
 * Single source of the recurring subscription price (IMPLEMENTATION_PLAN B2 / BR-13, BR-17, BR-21).
 *
 *  - commercial(): today's terms for a tier — from the admin settings (Ayarlar › Abunəliklər: price per tier,
 *    term in days), falling back to config/domain/subscriptions.php (15 AZN / 365 days) when unset or invalid.
 *    Given a person, device or complex, a special price set in the panel replaces the price (person → device →
 *    complex). It covers both tiers; the term stays the same.
 *  - chargeMinorFor(): what an order line for a subscription must cost.
 *      · a special price → that price, also for a subscription already waiting for payment
 *      · pending_payment (an initial purchase) → its price snapshot (taken at createPending)
 *      · anything else (a renewal)            → current commercial price — so a legacy comp
 *        (price_minor = 0) or an old annual row is never renewed at its stale snapshot
 *      · a non-positive amount is never charged — falls back to the commercial price.
 *
 * The one-off device sale price (devices.sale_price_minor) is deliberately NOT an input here.
 */
final class SubscriptionPriceResolver
{
    /** @return array{price_minor:int, term_days:int, currency:string} */
    public function commercial(SubscriptionTier $tier, ?int $userId = null, ?int $deviceId = null, ?int $complexId = null): array
    {
        $prices = (array) config('domain.subscriptions.default_prices_minor', []);
        $settings = app(SettingsService::class);
        $price = (int) $settings->value('subscriptions', $tier === SubscriptionTier::Additional ? 'price_additional_minor' : 'price_main_minor');
        $term = (int) $settings->value('subscriptions', 'subscription_duration_days');

        return [
            'price_minor' => $this->specialMinor($userId, $deviceId, $complexId)
                ?? ($price > 0 ? $price : (int) ($prices[$tier->priceKey()] ?? 0)),
            'term_days' => $term > 0 ? $term : (int) config('domain.subscriptions.term_days', 365),
            'currency' => (string) config('domain.subscriptions.currency', 'AZN'),
        ];
    }

    /** @return array{price_minor:int, term_days:int, currency:string} */
    public function forDeviceUser(SubscriptionTier $tier, DeviceUser $deviceUser): array
    {
        return $this->commercial($tier, ...$this->subjects($deviceUser));
    }

    /** @return array{price_minor:int, term_days:int, currency:string} */
    public function forSubscription(Subscription $subscription): array
    {
        return $this->commercial($subscription->tier, ...$this->subjects($subscription->deviceUser));
    }

    public function chargeMinorFor(?Subscription $subscription, OrderItemType $type): int
    {
        if ($subscription === null) {
            return $this->commercial($this->tierFor($type))['price_minor'];
        }

        $subjects = $this->subjects($subscription->deviceUser);
        $commercial = $this->commercial($subscription->tier, ...$subjects)['price_minor'];

        if ($subscription->status === SubscriptionStatus::PendingPayment
            && (int) $subscription->price_minor > 0
            && $this->specialMinor(...$subjects) === null) {
            return (int) $subscription->price_minor;
        }

        return $commercial;
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} person, device, complex */
    private function subjects(?DeviceUser $deviceUser): array
    {
        if ($deviceUser === null) {
            return [null, null, null];
        }
        $complexId = Device::query()->whereKey($deviceUser->device_id)->value('complex_id');

        return [(int) $deviceUser->user_id, (int) $deviceUser->device_id, $complexId !== null ? (int) $complexId : null];
    }

    private function specialMinor(?int $userId, ?int $deviceId, ?int $complexId): ?int
    {
        $subjects = array_filter([
            SubscriptionPriceScope::User->value => $userId,
            SubscriptionPriceScope::Device->value => $deviceId,
            SubscriptionPriceScope::Complex->value => $complexId,
        ], static fn (?int $id): bool => $id !== null);

        if ($subjects === []) {
            return null;
        }

        $rows = SubscriptionPriceOverride::query()
            ->where(function ($query) use ($subjects): void {
                foreach ($subjects as $scope => $id) {
                    $query->orWhere(fn ($q) => $q->where('scope', $scope)->where('subject_id', $id));
                }
            })
            ->pluck('price_minor', 'scope');

        foreach (SubscriptionPriceScope::cases() as $scope) { // declared in order of precedence
            if ($rows->has($scope->value)) {
                return (int) $rows[$scope->value];
            }
        }

        return null;
    }

    private function tierFor(OrderItemType $type): SubscriptionTier
    {
        return $type === OrderItemType::SubAdditional ? SubscriptionTier::Additional : SubscriptionTier::Main;
    }
}
