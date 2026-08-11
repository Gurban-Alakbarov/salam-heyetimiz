<?php

namespace App\Domain\Notifications\Adapters;

use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Enums\PushDeliveryStatus;

/**
 * Deterministic, credential-free [PushClient] used until the FCM adapter ships (Phase 4) and as the
 * test double (R-ARCH-08). It records every send for assertions; tokens listed in `invalidTokens`
 * return `TokenInvalid` (exercising push_invalid soft-flagging) and `failingTokens` return `Failed`.
 * Sends no real network traffic and needs no configuration.
 */
final class FakePushClient implements PushClient
{
    /** @var list<array{token: string, notification: array<string, mixed>, data: array<string, mixed>}> */
    public array $sent = [];

    /** @var list<string> tokens the fake reports as unregistered (→ TokenInvalid). */
    public array $invalidTokens = [];

    /** @var list<string> tokens the fake reports as a transient failure (→ Failed). */
    public array $failingTokens = [];

    public function send(string $token, array $notification, array $data): PushDeliveryStatus
    {
        $this->sent[] = ['token' => $token, 'notification' => $notification, 'data' => $data];

        if (in_array($token, $this->invalidTokens, true)) {
            return PushDeliveryStatus::TokenInvalid;
        }

        if (in_array($token, $this->failingTokens, true)) {
            return PushDeliveryStatus::Failed;
        }

        return PushDeliveryStatus::Delivered;
    }
}
