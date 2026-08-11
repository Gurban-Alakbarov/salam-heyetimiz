<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\AuthActorKind;
use App\Domain\Auth\Enums\AuthOutcome;
use App\Domain\Auth\Models\AuthAttempt;
use Illuminate\Support\Facades\Context;

/**
 * Append-only brute-force / abuse log (DB Arch §1.6 / R-SEC-16). Written on every
 * login attempt — success and failure — so lockout and anomaly detection have a source
 * of truth independent of the rate limiter. The identifier is a phone or email, never a
 * secret; codes/passwords are never recorded (R-SEC-15).
 */
final class AuthAttemptRecorder
{
    public function record(
        AuthActorKind $actorKind,
        string $identifier,
        AuthOutcome $outcome,
        string $ip,
        ?string $userAgent = null,
    ): void {
        AuthAttempt::query()->create([
            'actor_kind' => $actorKind,
            'identifier' => $identifier,
            'outcome' => $outcome,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'request_id' => $this->requestId(),
        ]);
    }

    private function requestId(): ?string
    {
        $id = Context::get('request_id');

        return is_string($id) ? mb_substr($id, 0, 26) : null;
    }
}
