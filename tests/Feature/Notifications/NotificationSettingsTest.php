<?php

use App\Domain\Notifications\DTOs\NotificationRequest;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\NotificationDispatcher;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| O13 — a user can switch off the push of the visitor-opened notice and of admin announcements (not billing
| reminders); a switched-off push is not sent, the in-app inbox row still is.
*/

beforeEach(fn () => (new NotificationTemplatesSeeder)->run());

function o13Send(int $userId, string $templateKey, NotificationType $type, string $dedupe): void
{
    app(NotificationDispatcher::class)->dispatch(new NotificationRequest(
        userId: $userId,
        type: $type,
        templateKey: $templateKey,
        title: 'Başlıq',
        body: 'Mətn',
        ids: [],
        channels: [NotificationChannel::Push, NotificationChannel::Inapp],
        dedupeKey: $dedupe,
    ));
}

it('lists the switchable notifications, all on by default', function () {
    $user = makeUser();

    $this->actingAs($user, 'user')->getJson('/v1/me/notification-settings')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['template_key' => 'device.opened', 'push_enabled' => true],
            ['template_key' => 'system.admin_campaign', 'push_enabled' => true],
        ]]);
});

it('stops the push of a switched-off notification but keeps it in the inbox', function () {
    Bus::fake();
    $user = makeUser();

    $this->actingAs($user, 'user')->putJson('/v1/me/notification-settings', ['template_key' => 'device.opened', 'push_enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.0.push_enabled', false)
        ->assertJsonPath('data.1.push_enabled', true);

    o13Send($user->id, 'device.opened', NotificationType::VisitorLinkUsed, 'visitor_opened:1');
    o13Send($user->id, 'system.admin_campaign', NotificationType::SystemAnnouncement, 'campaign:1');

    expect(Notification::query()->where('template_key', 'device.opened')->pluck('channel')->map->value->all())->toBe(['inapp'])
        ->and(Notification::query()->where('template_key', 'system.admin_campaign')->count())->toBe(2);
    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);

    // and back on
    $this->actingAs($user, 'user')->putJson('/v1/me/notification-settings', ['template_key' => 'device.opened', 'push_enabled' => true])->assertOk();
    o13Send($user->id, 'device.opened', NotificationType::VisitorLinkUsed, 'visitor_opened:2');
    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 2);
});

it('keeps billing reminders forced', function () {
    $user = makeUser();

    $this->actingAs($user, 'user')->putJson('/v1/me/notification-settings', ['template_key' => 'subscription.expired', 'push_enabled' => false])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'notification_not_mutable');
    $this->actingAs($user, 'user')->putJson('/v1/me/notification-settings', ['template_key' => 'no.such.template', 'push_enabled' => false])
        ->assertStatus(422);
});

it('is per user', function () {
    Bus::fake();
    $a = makeUser('+994501300001');
    $b = makeUser('+994501300002');
    $this->actingAs($a, 'user')->putJson('/v1/me/notification-settings', ['template_key' => 'device.opened', 'push_enabled' => false])->assertOk();

    o13Send($b->id, 'device.opened', NotificationType::VisitorLinkUsed, 'visitor_opened:9');

    expect(Notification::query()->where('user_id', $b->id)->count())->toBe(2);
});
