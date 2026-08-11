<?php

namespace App\Domain\Subscriptions\Services;

use App\Domain\Payments\Enums\CardTokenStatus;
use App\Domain\Payments\Models\CardToken;
use App\Domain\Subscriptions\Exceptions\AutoRenewUnavailableException;
use App\Domain\Subscriptions\Models\Subscription;

/**
 * Auto-renew toggle (openapi toggleAutoRenew; Tech Spec §13.8). Auto-charge is P2: until Kapital
 * tokenization is available (config domain.subscriptions.auto_renew_enabled) enabling is rejected
 * with 409. Disabling always succeeds. When enabled in the future, a valid active card token
 * belonging to the subscription's user is required.
 */
final class AutoRenewService
{
    public function toggle(Subscription $subscription, bool $enable, ?int $cardTokenId): Subscription
    {
        if (! $enable) {
            $subscription->forceFill(['auto_renew' => false])->save();

            return $subscription;
        }

        if (! (bool) config('domain.subscriptions.auto_renew_enabled', false)) {
            throw new AutoRenewUnavailableException('Auto-renew is not available yet (no tokenization).');
        }

        $token = $this->resolveToken($subscription, $cardTokenId);

        $subscription->forceFill([
            'auto_renew' => true,
            'card_token_id' => $token->id,
        ])->save();

        return $subscription;
    }

    private function resolveToken(Subscription $subscription, ?int $cardTokenId): CardToken
    {
        $ownerUserId = (int) $subscription->deviceUser()->value('user_id');

        $token = $cardTokenId === null ? null : CardToken::query()
            ->whereKey($cardTokenId)
            ->where('user_id', $ownerUserId)
            ->where('status', CardTokenStatus::Active->value)
            ->first();

        if ($token === null) {
            throw new AutoRenewUnavailableException('A valid active card token is required to enable auto-renew.');
        }

        return $token;
    }
}
