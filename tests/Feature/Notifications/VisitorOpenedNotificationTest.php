<?php

use App\Domain\Auth\Models\UserDevice;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Devices\Models\Device;
use App\Domain\Notifications\Adapters\FakePushClient;
use App\Domain\Notifications\Contracts\PushClient;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Visitor\Enums\VisitorAccessType;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| SendVisitorOpenedNotification (INVENTORY §1 MVP #1): OpenCommandCompleted[Opened,Visitor] → the link
| owner gets a push + inapp `device.opened` notification (dedupe visitor_opened:{command_id}). Never on
| dispatched / non-visitor / missing link. DeviceComm/Visitor logic is untouched.
*/

beforeEach(fn () => (new NotificationTemplatesSeeder)->run());

function fireVisitorOpen(Device $device, int $linkId, array $attrs = []): OpenCommand
{
    $now = now();
    $command = OpenCommand::query()->create(array_merge([
        'partition_key' => (int) $now->format('Ym'),
        'device_id' => $device->getKey(),
        'user_id' => null,            // visitor opens carry no roster user (the link is the authority)
        'device_user_id' => null,
        'driver' => $device->driver_type->value,
        'state' => 'opened',
        'attempts' => 1,
        'requested_at' => $now,
        'source' => 'visitor',
        'metadata' => ['visitor_link_id' => $linkId],
        'created_at' => $now,
    ], $attrs));

    OpenCommandCompleted::dispatch($command);

    return $command;
}

it('notifies the link creator on a confirmed visitor open (push queued + inapp sent)', function () {
    Bus::fake();
    $resident = makeUser('+994506000001');
    $device = makeOwnedDevice(makeUser('+994506000002'), 'VN-1', '+994700600001');
    $device->update(['location_label' => 'Blok A qapısı']);
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    $command = fireVisitorOpen($device, (int) $link->id);

    $rows = Notification::query()->where('user_id', $resident->id)->get();
    expect($rows)->toHaveCount(2);

    $push = $rows->firstWhere('channel', NotificationChannel::Push);
    $inapp = $rows->firstWhere('channel', NotificationChannel::Inapp);

    expect($push->status)->toBe(NotificationStatus::Queued)
        ->and($inapp->status)->toBe(NotificationStatus::Sent)
        ->and($push->template_key)->toBe('device.opened')
        ->and($push->campaign_id)->toBeNull()
        ->and($push->dedupe_key)->toBe('visitor_opened:'.$command->id)
        ->and($push->payload['type'])->toBe('visitor_link_used')
        ->and($push->payload['title'])->toBe('Qonaq daxil oldu')
        ->and($push->payload['body'])->toBe('Kuryer Blok A qapısı qapısını açdı')
        ->and($push->payload['ids'])->toBe(['visitor_link_id' => (int) $link->id, 'device_id' => (int) $device->id]);

    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
});

it('falls back to the device owner when the link has no creator user (admin-created)', function () {
    Bus::fake();
    $owner = makeUser('+994506000003');
    $device = makeOwnedDevice($owner, 'VN-2', '+994700600002');
    $device->update(['location_label' => 'Qapı 2']);
    $admin = makeSuperAdmin('vlink-admin1@salamhayetimiz.az');
    [$link] = mintVisitorLink($device, $admin, VisitorAccessType::TimeLimited, 60, 'Qonaq');

    fireVisitorOpen($device, (int) $link->id);

    expect(Notification::query()->where('user_id', $owner->id)->count())->toBe(2);
});

it('renders device.opened in the recipient preferred language', function (string $lang, string $title, string $body) {
    Bus::fake(); // each dataset case runs on a fresh DB (RefreshDatabase), so fixed ids are fine
    $resident = makeUser('+994506010001');
    $resident->update(['preferred_language' => $lang]);
    $device = makeOwnedDevice(makeUser('+994506010002'), 'VL-1', '+994700601001');
    $device->update(['location_label' => 'Qapı']);
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    fireVisitorOpen($device, (int) $link->id);

    $push = Notification::query()->where('user_id', $resident->id)->where('channel', 'push')->first();
    expect($push->payload['title'])->toBe($title)
        ->and($push->payload['body'])->toBe($body);
})->with([
    'az' => ['az', 'Qonaq daxil oldu', 'Kuryer Qapı qapısını açdı'],
    'ru' => ['ru', 'Гость вошёл', 'Kuryer открыл(а) дверь Qapı'],
    'en' => ['en', 'Visitor entered', 'Kuryer opened Qapı'],
]);

it('does not notify on a dispatched (unconfirmed) visitor open', function () {
    Bus::fake();
    $resident = makeUser('+994506000010');
    $device = makeOwnedDevice(makeUser('+994506000011'), 'VN-4', '+994700600004');
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    fireVisitorOpen($device, (int) $link->id, ['state' => 'dispatched']);

    expect(Notification::query()->where('user_id', $resident->id)->count())->toBe(0);
    Bus::assertNotDispatched(SendPushNotificationJob::class);
});

it('does not notify on a non-visitor open', function () {
    Bus::fake();
    $resident = makeUser('+994506000013');
    $device = makeOwnedDevice(makeUser('+994506000012'), 'VN-5', '+994700600005');
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    fireVisitorOpen($device, (int) $link->id, ['source' => 'mobile']);

    expect(Notification::query()->count())->toBe(0);
});

it('does not notify when the command carries no visitor_link_id', function () {
    Bus::fake();
    $device = makeOwnedDevice(makeUser('+994506000014'), 'VN-6', '+994700600006');

    fireVisitorOpen($device, 0, ['metadata' => []]);

    expect(Notification::query()->count())->toBe(0);
});

it('safely skips when there is no recipient and no owner fallback', function () {
    Bus::fake();
    $device = makeActiveDevice('VN-7', '+994700600007'); // unowned → owner_user_id null
    $admin = makeSuperAdmin('vlink-admin2@salamhayetimiz.az');
    [$link] = mintVisitorLink($device, $admin, VisitorAccessType::TimeLimited, 60, 'Qonaq');

    fireVisitorOpen($device, (int) $link->id);

    expect(Notification::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendPushNotificationJob::class);
});

it('is idempotent — the same command twice creates no duplicate rows or push enqueue', function () {
    Bus::fake();
    $resident = makeUser('+994506000015');
    $device = makeOwnedDevice(makeUser('+994506000016'), 'VN-8', '+994700600008');
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    $now = now();
    $command = OpenCommand::query()->create([
        'partition_key' => (int) $now->format('Ym'),
        'device_id' => $device->id, 'user_id' => null, 'device_user_id' => null,
        'driver' => $device->driver_type->value, 'state' => 'opened', 'attempts' => 1,
        'requested_at' => $now, 'source' => 'visitor',
        'metadata' => ['visitor_link_id' => (int) $link->id], 'created_at' => $now,
    ]);
    OpenCommandCompleted::dispatch($command);
    OpenCommandCompleted::dispatch($command); // replay

    expect(Notification::query()->where('user_id', $resident->id)->count())->toBe(2);
    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
});

it('fans the push out to every active install via the bound PushClient', function () {
    $resident = makeUser('+994506000020');
    UserDevice::query()->create(['user_id' => $resident->id, 'install_uuid' => 'vn-a', 'platform' => 'ios', 'push_token' => 'vtok-a', 'push_invalid' => false]);
    UserDevice::query()->create(['user_id' => $resident->id, 'install_uuid' => 'vn-b', 'platform' => 'android', 'push_token' => 'vtok-b', 'push_invalid' => false]);
    $device = makeOwnedDevice(makeUser('+994506000021'), 'VN-9', '+994700600009');
    $device->update(['location_label' => 'Qapı']);
    [$link] = mintVisitorLink($device, $resident, VisitorAccessType::TimeLimited, 60, 'Kuryer');

    $fake = new FakePushClient;
    app()->instance(PushClient::class, $fake);

    fireVisitorOpen($device, (int) $link->id); // sync queue → SendPushNotificationJob runs inline

    expect($fake->sent)->toHaveCount(2)
        ->and(collect($fake->sent)->pluck('token')->all())->toEqualCanonicalizing(['vtok-a', 'vtok-b'])
        ->and(Notification::query()->where('user_id', $resident->id)->where('channel', 'push')->first()->status)
        ->toBe(NotificationStatus::Sent);
});
