<?php

use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;

/*
| NotificationDispatcher (Backend Arch §14.9): one row per channel, in-app sent-on-write,
| push enqueue, and (user_id, dedupe_key, channel) idempotency (R-NOT-04/05/06/08).
*/

function visitorRequest(User $user, array $o = []): NotificationRequest
{
    return new NotificationRequest(
        userId: (int) $user->getKey(),
        type: NotificationType::VisitorLinkUsed,
        templateKey: 'device.opened',
        title: 'Qonaq daxil oldu',
        body: 'Kuryer baryeri açdı',
        ids: ['visitor_link_id' => 5, 'device_id' => 3],
        channels: $o['channels'] ?? [NotificationChannel::Push, NotificationChannel::Inapp],
        dedupeKey: $o['dedupeKey'] ?? 'visitor_opened:42',
    );
}

it('materialises one row per channel carrying the rendered payload', function () {
    Bus::fake();
    $user = makeUser('+994500000201');

    app(NotificationDispatcher::class)->dispatch(visitorRequest($user));

    $rows = Notification::query()->where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(2);

    $push = $rows->firstWhere('channel', NotificationChannel::Push);
    $inapp = $rows->firstWhere('channel', NotificationChannel::Inapp);

    expect($push)->not->toBeNull()
        ->and($inapp)->not->toBeNull()
        ->and($push->template_key)->toBe('device.opened')
        ->and($push->payload['type'])->toBe('visitor_link_used')
        ->and($push->payload['title'])->toBe('Qonaq daxil oldu')
        ->and($push->payload['ids'])->toBe(['visitor_link_id' => 5, 'device_id' => 3])
        ->and($push->dedupe_key)->toBe('visitor_opened:42')
        ->and($push->campaign_id)->toBeNull()
        ->and((int) $push->partition_key)->toBe((int) now()->format('Ym'));
});

it('marks in-app sent on write but leaves push queued for the send job', function () {
    Bus::fake();
    $user = makeUser('+994500000202');

    app(NotificationDispatcher::class)->dispatch(visitorRequest($user));

    $push = Notification::query()->where('user_id', $user->id)->where('channel', 'push')->first();
    $inapp = Notification::query()->where('user_id', $user->id)->where('channel', 'inapp')->first();

    expect($push->status)->toBe(NotificationStatus::Queued)
        ->and($push->sent_at)->toBeNull()
        ->and($inapp->status)->toBe(NotificationStatus::Sent)
        ->and($inapp->sent_at)->not->toBeNull();
});

it('enqueues the push send job once, only for the push channel', function () {
    Bus::fake();
    $user = makeUser('+994500000203');

    app(NotificationDispatcher::class)->dispatch(visitorRequest($user));

    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
    Bus::assertDispatched(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($user) {
        $push = Notification::query()->where('user_id', $user->id)->where('channel', 'push')->first();

        return $job->notificationId === (int) $push->id
            && $job->partitionKey === (int) now()->format('Ym');
    });
});

it('is idempotent on the dedupe key — a replay adds no rows and enqueues nothing new', function () {
    Bus::fake();
    $user = makeUser('+994500000204');

    app(NotificationDispatcher::class)->dispatch(visitorRequest($user));
    app(NotificationDispatcher::class)->dispatch(visitorRequest($user)); // identical dedupe_key

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(2);
    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
});

it('keeps admin campaign notifications on their own campaign id and reserved template key', function () {
    Bus::fake();
    $user = makeUser('+994500000205');

    app(NotificationDispatcher::class)->dispatch(new NotificationRequest(
        userId: (int) $user->getKey(),
        type: NotificationType::SystemAnnouncement,
        templateKey: 'system.admin_campaign',
        title: 'Elan',
        body: 'Sabah texniki iş var',
        ids: [],
        channels: [NotificationChannel::Push, NotificationChannel::Inapp],
        dedupeKey: 'campaign:7:'.$user->id,
        campaignId: 7,
    ));

    $push = Notification::query()->where('user_id', $user->id)->where('channel', 'push')->first();
    expect((int) $push->campaign_id)->toBe(7)
        ->and($push->template_key)->toBe('system.admin_campaign')
        ->and($push->payload['type'])->toBe('system_announcement');
});
