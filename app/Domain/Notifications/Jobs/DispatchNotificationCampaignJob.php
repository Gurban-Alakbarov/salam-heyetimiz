<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\CampaignStatus;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Admin campaign fan-out (ADMIN_SPEC): materialises one system_announcement notification per recipient via
 * the shared [NotificationDispatcher] — dedupe `campaign:{campaign_id}:{user_id}`, so a retry never
 * duplicates a recipient's rows. Channels come from the `system.admin_campaign` template's
 * `default_channels_mask` (R-NOT-04 — fixed push + inapp, no campaign channel mask). The admin free-text
 * title/body are delivered as-is (single language, no translation — R-NOT-20). Per-recipient multi-device
 * push fan-out stays in SendPushNotificationJob (Phase 2) — this writes rows only. Runs on the
 * `notifications` queue.
 */
class DispatchNotificationCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK = 500;

    /** @param  array<int, int>  $userIds */
    public function __construct(public readonly int $campaignId, public readonly array $userIds)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationDispatcher $dispatcher, Clock $clock): void
    {
        /** @var NotificationCampaign|null $campaign */
        $campaign = NotificationCampaign::query()->find($this->campaignId);
        if ($campaign === null || $campaign->status->isTerminal()) {
            return;
        }

        $template = NotificationTemplate::query()
            ->where('template_key', 'system.admin_campaign')
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            $campaign->update(['status' => CampaignStatus::Failed->value]);
            Log::error('admin campaign fan-out aborted: system.admin_campaign template missing', ['campaign_id' => $this->campaignId]);

            return;
        }

        $channels = NotificationChannel::fromMask((int) $template->default_channels_mask);
        $campaign->update(['status' => CampaignStatus::Sending->value]);

        $sent = 0;
        $failed = 0;
        foreach (array_chunk($this->userIds, self::CHUNK) as $chunk) {
            foreach ($chunk as $userId) {
                try {
                    $dispatcher->dispatch(new NotificationRequest(
                        userId: (int) $userId,
                        type: NotificationType::SystemAnnouncement,
                        templateKey: 'system.admin_campaign',
                        title: $campaign->title,
                        body: $campaign->body,
                        ids: ['campaign_id' => (int) $campaign->getKey()],
                        channels: $channels,
                        dedupeKey: 'campaign:'.$campaign->getKey().':'.(int) $userId,
                        campaignId: (int) $campaign->getKey(),
                    ));
                    $sent++;
                } catch (\Throwable) {
                    $failed++;
                    Log::warning('admin campaign recipient dispatch failed', [
                        'campaign_id' => $this->campaignId,
                        'user_id' => (int) $userId,
                    ]);
                }
            }
        }

        $campaign->update([
            'status' => ($failed > 0 && $sent === 0 ? CampaignStatus::Failed : CampaignStatus::Sent)->value,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => $clock->now(),
        ]);
    }
}
