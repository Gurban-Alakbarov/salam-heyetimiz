<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Notifications\Enums\CampaignStatus;
use App\Domain\Notifications\Exceptions\ConfirmationRequiredException;
use App\Domain\Notifications\Exceptions\IdempotencyMismatchException;
use App\Domain\Notifications\Jobs\DispatchNotificationCampaignJob;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates an admin campaign SEND (ADMIN_SPEC): confirmation gate → idempotency → audience resolution
 * → campaign record → queued fan-out. Confirmation and idempotency are enforced here, never in the
 * transport. Idempotency is Redis/cache-backed (LOCKED DECISION 4): the (actor, key) pair caches the
 * campaign for 24 h; a replay with the same body returns the same campaign (no second fan-out), a replay
 * with a different body is a 409 mismatch. No campaign channel mask — channels are template-driven (R-NOT-04).
 */
final class NotificationCampaignService
{
    /** Idempotency cache TTL — 24 h (ADMIN_SPEC / OpenAPI IdempotencyKeyRequired). */
    private const IDEMPOTENCY_TTL_SECONDS = 86400;

    public function __construct(
        private readonly CampaignAudienceResolver $resolver,
        private readonly Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated { type, title, body, language, audience }
     */
    public function send(AdminUser $actor, array $data, bool $confirmed, ?int $scopeComplexId, string $idempotencyKey): NotificationCampaign
    {
        // Mandatory send confirmation (D8) — nothing is created without it.
        if (! $confirmed) {
            throw new ConfirmationRequiredException;
        }

        // Idempotency is isolated by (actor, key); the fingerprint pins the request body so a reused key
        // with a changed body is a mismatch rather than a silent replay.
        $cacheKey = 'notif:campaign:idem:'.$actor->getKey().':'.$idempotencyKey;
        $fingerprint = hash('sha256', json_encode([
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'language' => $data['language'],
            'audience' => $data['audience'],
        ], JSON_THROW_ON_ERROR));

        return Cache::lock($cacheKey.':lock', 10)->block(5, function () use ($cacheKey, $fingerprint, $actor, $data, $scopeComplexId): NotificationCampaign {
            $existing = Cache::get($cacheKey);
            if (is_array($existing)) {
                if (($existing['fingerprint'] ?? null) !== $fingerprint) {
                    throw new IdempotencyMismatchException;
                }

                $campaign = NotificationCampaign::query()->find($existing['campaign_id'] ?? 0);
                if ($campaign !== null) {
                    return $campaign; // idempotent replay — the original campaign, no second fan-out.
                }
            }

            $campaign = $this->create($actor, $data, $scopeComplexId);
            Cache::put($cacheKey, ['fingerprint' => $fingerprint, 'campaign_id' => $campaign->getKey()], self::IDEMPOTENCY_TTL_SECONDS);

            return $campaign;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(AdminUser $actor, array $data, ?int $scopeComplexId): NotificationCampaign
    {
        $audience = $data['audience'];
        $userIds = $this->resolver->resolve($audience, $scopeComplexId);

        /** @var NotificationCampaign $campaign */
        $campaign = NotificationCampaign::query()->create([
            'created_by_admin_id' => $actor->getKey(),
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'],
            'language' => $data['language'],
            'audience_scope' => $audience['scope'],
            'audience_filter' => $this->recordedAudience($audience),
            'status' => CampaignStatus::Queued->value,
            'total_recipients' => count($userIds),
            'sent_count' => 0,
            'failed_count' => 0,
            'confirmed_at' => $this->clock->now(),
        ]);

        // Fan-out is queued (202 accepted). Per-recipient multi-device push stays in SendPushNotificationJob.
        DispatchNotificationCampaignJob::dispatch((int) $campaign->getKey(), $userIds);

        return $campaign;
    }

    /**
     * The audience detail recorded on the campaign (scope lives in its own column). Reference only — the
     * resolved recipient id list drives the fan-out.
     *
     * @param  array<string, mixed>  $audience
     * @return array<string, mixed>|null
     */
    private function recordedAudience(array $audience): ?array
    {
        $recorded = array_filter([
            'user_ids' => $audience['user_ids'] ?? null,
            'filter' => $audience['filter'] ?? null,
            'complex_id' => $audience['complex_id'] ?? null,
        ], static fn ($v): bool => $v !== null);

        return $recorded === [] ? null : $recorded;
    }
}
