<?php

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Auth\Services\UserDeviceService;
use App\Domain\Notifications\Adapters\FakePushClient;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;

/*
| SendPushNotificationJob (Backend Arch §14.9): multi-device fan-out, hybrid payload,
| invalid-token soft-flagging (R-NOT-14), and terminal sent/failed on the partitioned row.
*/

function pushRow(User $user, array $o = []): Notification
{
    return Notification::query()->create(array_merge([
        'partition_key' => (int) now()->format('Ym'),
        'user_id' => $user->getKey(),
        'campaign_id' => null,
        'template_key' => 'device.opened',
        'channel' => 'push',
        'payload' => [
            'title' => 'Qonaq daxil oldu',
            'body' => 'Kuryer baryeri açdı',
            'type' => 'visitor_link_used',
            'ids' => ['visitor_link_id' => 5, 'device_id' => 3],
        ],
        'dedupe_key' => 'visitor_opened:42',
        'status' => 'queued',
    ], $o));
}

function install(User $user, string $uuid, ?string $token, array $o = []): UserDevice
{
    return UserDevice::query()->create(array_merge([
        'user_id' => $user->getKey(),
        'install_uuid' => $uuid,
        'platform' => 'ios',
        'push_token' => $token,
        'push_invalid' => false,
    ], $o));
}

function runPushJob(Notification $row, FakePushClient $fake): void
{
    (new SendPushNotificationJob((int) $row->id, (int) $row->partition_key))
        ->handle($fake, app(UserDeviceService::class), app(Clock::class));
}

it('fans out to every active install and marks the row sent', function () {
    $user = makeUser('+994500000301');
    install($user, 'uuid-a', 'tok-a');
    install($user, 'uuid-b', 'tok-b');
    $row = pushRow($user);
    $fake = new FakePushClient;

    runPushJob($row, $fake);

    expect($fake->sent)->toHaveCount(2)
        ->and(collect($fake->sent)->pluck('token')->all())->toEqualCanonicalizing(['tok-a', 'tok-b'])
        ->and($row->refresh()->status)->toBe(NotificationStatus::Sent)
        ->and($row->sent_at)->not->toBeNull()
        ->and($row->failure_reason)->toBeNull();
});

it('sends the hybrid payload with type, notification_id and deep-link ids (no secrets)', function () {
    $user = makeUser('+994500000302');
    install($user, 'uuid-a', 'tok-a');
    $row = pushRow($user);
    $fake = new FakePushClient;

    runPushJob($row, $fake);

    $message = $fake->sent[0];
    expect($message['notification'])->toBe(['title' => 'Qonaq daxil oldu', 'body' => 'Kuryer baryeri açdı'])
        ->and($message['data']['type'])->toBe('visitor_link_used')
        ->and($message['data']['notification_id'])->toBe((string) $row->id)
        ->and($message['data']['ids'])->toBe(['visitor_link_id' => 5, 'device_id' => 3]);
});

it('soft-invalidates an unregistered token but still delivers to the others', function () {
    $user = makeUser('+994500000303');
    install($user, 'uuid-bad', 'tok-bad');
    install($user, 'uuid-good', 'tok-good');
    $row = pushRow($user);
    $fake = new FakePushClient;
    $fake->invalidTokens = ['tok-bad'];

    runPushJob($row, $fake);

    expect(UserDevice::query()->where('install_uuid', 'uuid-bad')->first()->push_invalid)->toBeTrue()
        ->and(UserDevice::query()->where('install_uuid', 'uuid-good')->first()->push_invalid)->toBeFalse()
        ->and($row->refresh()->status)->toBe(NotificationStatus::Sent); // one good delivery wins
});

it('marks the row failed when every token is invalid', function () {
    $user = makeUser('+994500000304');
    install($user, 'uuid-bad', 'tok-bad');
    $row = pushRow($user);
    $fake = new FakePushClient;
    $fake->invalidTokens = ['tok-bad'];

    runPushJob($row, $fake);

    expect($row->refresh()->status)->toBe(NotificationStatus::Failed)
        ->and($row->failure_reason)->toBe('push_invalid')
        ->and(UserDevice::query()->where('install_uuid', 'uuid-bad')->first()->push_invalid)->toBeTrue();
});

it('fails with no_active_device and sends nothing when no install is eligible', function () {
    $user = makeUser('+994500000305');
    install($user, 'uuid-revoked', 'tok-r', ['revoked_at' => now()]);
    install($user, 'uuid-invalid', 'tok-i', ['push_invalid' => true]);
    install($user, 'uuid-null', null);
    $row = pushRow($user);
    $fake = new FakePushClient;

    runPushJob($row, $fake);

    expect($fake->sent)->toHaveCount(0)
        ->and($row->refresh()->status)->toBe(NotificationStatus::Failed)
        ->and($row->failure_reason)->toBe('no_active_device');
});

it('is a no-op on an already-delivered row (retry safety)', function () {
    $user = makeUser('+994500000306');
    install($user, 'uuid-a', 'tok-a');
    $row = pushRow($user, ['status' => 'sent', 'sent_at' => now()]);
    $fake = new FakePushClient;

    runPushJob($row, $fake);

    expect($fake->sent)->toHaveCount(0);
});
