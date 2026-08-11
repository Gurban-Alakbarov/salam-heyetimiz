<?php

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Jobs\SendPushNotificationJob;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Subscriptions\Enums\ReminderKind;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Events\SubscriptionExpired;
use App\Domain\Subscriptions\Events\SubscriptionExpiringSoon;
use App\Domain\Subscriptions\Services\SubscriptionService;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| Subscription lifecycle notifications (INVENTORY §2; Phase 4A). Existing Subscriptions events →
| push + inapp `subscription.*` notifications to the entitlement owner (device_user_id → user_id),
| category billing, LOCKED dedupe. SubscriptionActivated notifies ONLY a real paid activation; the
| admin comp-grant path (no billing period) is a no-op. Subscriptions dispatch sites are untouched.
*/

beforeEach(fn () => (new NotificationTemplatesSeeder)->run());

/** A recipient user + active device + owner roster row + subscription; returns [user, subscription, device]. */
function subNotifFixture(string $phone, string $serial, string $sim, array $subAttrs = []): array
{
    $user = makeUser($phone);
    $device = makeActiveDevice($serial, $sim);
    $deviceUser = makeDeviceUser($user, $device, 'owner');
    $subscription = makeSubscription($deviceUser, $subAttrs);

    return [$user, $subscription, $device];
}

it('notifies the owner when a subscription expires (push queued + inapp sent)', function () {
    Bus::fake();
    [$user, $sub, $device] = subNotifFixture('+994551000001', 'SN-EXP-1', '+994700700001');

    SubscriptionExpired::dispatch($sub);

    $rows = Notification::query()->where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(2);

    $push = $rows->firstWhere('channel', NotificationChannel::Push);
    $inapp = $rows->firstWhere('channel', NotificationChannel::Inapp);

    expect($push->status)->toBe(NotificationStatus::Queued)
        ->and($inapp->status)->toBe(NotificationStatus::Sent)
        ->and($push->template_key)->toBe('subscription.expired')
        ->and($push->campaign_id)->toBeNull()
        ->and($push->payload['type'])->toBe('subscription_expired')
        ->and($push->dedupe_key)->toBe('sub_expired:'.$sub->id)
        ->and($push->payload['ids'])->toBe(['subscription_id' => (int) $sub->id, 'device_id' => (int) $device->id]);

    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
});

it('maps each expiring threshold to its template with a per-cycle dedupe', function (string $kind, string $templateKey) {
    Bus::fake();
    [$user, $sub] = subNotifFixture('+994551000010', 'SN-SOON-1', '+994700700010');

    SubscriptionExpiringSoon::dispatch($sub, ReminderKind::from($kind));

    $push = Notification::query()->where('user_id', $user->id)->where('channel', 'push')->first();
    expect($push->template_key)->toBe($templateKey)
        ->and($push->payload['type'])->toBe('subscription_expiring')
        ->and($push->dedupe_key)->toBe('sub_expiring:'.$sub->id.':'.$kind);
})->with([
    'd30' => ['d30', 'subscription.expiring_30d'],
    'd15' => ['d15', 'subscription.expiring_15d'],
    'd7' => ['d7', 'subscription.expiring_7d'],
    'd1' => ['d1', 'subscription.expiring_1d'],
]);

it('renders subscription copy in the recipient preferred language', function (string $lang, string $body) {
    Bus::fake(); // each dataset case runs on a fresh DB (RefreshDatabase), so fixed serial/sim are safe
    [$user, $sub] = subNotifFixture('+994551000020', 'SN-LANG-1', '+994700700020');
    $user->update(['preferred_language' => $lang]);

    SubscriptionExpired::dispatch($sub);

    $inapp = Notification::query()->where('user_id', $user->id)->where('channel', 'inapp')->first();
    expect($inapp->payload['body'])->toBe($body);
})->with([
    'az' => ['az', 'Abunəliyiniz başa çatıb'],
    'ru' => ['ru', 'Ваша подписка истекла'],
    'en' => ['en', 'Your subscription has expired'],
]);

it('notifies on a real paid activation, keyed by the fresh Initial period', function () {
    Bus::fake();
    $user = makeUser('+994551000030');
    $device = makeActiveDevice('SN-ACT-1', '+994700700030');
    $deviceUser = makeDeviceUser($user, $device, 'owner');
    $sub = makeSubscription($deviceUser, ['status' => SubscriptionStatus::PendingPayment, 'ends_at' => now()]);
    $order = makePaidOrder($user, 1200, 'KB-ACT-1');

    $sub = app(SubscriptionService::class)->activate($sub, $order, 1200);

    $initialPeriodId = (int) $sub->periods()->where('kind', 'initial')->value('id');
    $push = Notification::query()->where('user_id', $user->id)->where('template_key', 'subscription.activated')->where('channel', 'push')->first();

    expect($push)->not->toBeNull()
        ->and($push->payload['type'])->toBe('subscription_activated')
        ->and($push->dedupe_key)->toBe('sub_activated:'.$sub->id.':'.$initialPeriodId);
});

it('does NOT notify on an admin comp grant (no billing period → no receipt)', function () {
    Bus::fake();
    $user = makeUser('+994551000040');
    $device = makeActiveDevice('SN-GRANT-1', '+994700700040');
    $deviceUser = makeDeviceUser($user, $device, 'owner');

    app(SubscriptionService::class)->grantManual($deviceUser, SubscriptionTier::Main, 365);

    expect(Notification::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendPushNotificationJob::class);
});

it('notifies on a renewal, keyed by the new Renewal period', function () {
    Bus::fake();
    $user = makeUser('+994551000050');
    $device = makeActiveDevice('SN-REN-1', '+994700700050');
    $deviceUser = makeDeviceUser($user, $device, 'owner');
    $sub = makeSubscription($deviceUser, ['ends_at' => now()->addDays(10)]);
    $order = makePaidOrder($user, 1200, 'KB-REN-1');

    $sub = app(SubscriptionService::class)->renew($sub, $order, 1200);

    $renewalPeriodId = (int) $sub->periods()->where('kind', 'renewal')->orderByDesc('id')->value('id');
    $push = Notification::query()->where('user_id', $user->id)->where('template_key', 'subscription.renewed')->where('channel', 'push')->first();

    expect($push)->not->toBeNull()
        ->and($push->payload['type'])->toBe('subscription_renewed')
        ->and($push->dedupe_key)->toBe('sub_renewed:'.$sub->id.':'.$renewalPeriodId);
});

it('creates exactly the push + inapp rows for a subscription notification', function () {
    Bus::fake();
    [$user, $sub] = subNotifFixture('+994551000055', 'SN-CH-1', '+994700700055');

    SubscriptionExpiringSoon::dispatch($sub, ReminderKind::D15);

    $channels = Notification::query()->where('user_id', $user->id)->pluck('channel')->map->value->sort()->values()->all();
    expect($channels)->toBe(['inapp', 'push']);
});

it('is idempotent — the same expiring event twice creates no duplicate rows or push enqueue', function () {
    Bus::fake();
    [$user, $sub] = subNotifFixture('+994551000060', 'SN-DUP-1', '+994700700060');

    SubscriptionExpiringSoon::dispatch($sub, ReminderKind::D7);
    SubscriptionExpiringSoon::dispatch($sub, ReminderKind::D7); // replay

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(2);
    Bus::assertDispatchedTimes(SendPushNotificationJob::class, 1);
});

it('targets exactly the subscription owner, never another user', function () {
    Bus::fake();
    $owner = makeUser('+994551000070');
    $other = makeUser('+994551000071');
    $device = makeActiveDevice('SN-TGT-1', '+994700700070');
    $deviceUser = makeDeviceUser($owner, $device, 'owner');
    $sub = makeSubscription($deviceUser);

    SubscriptionExpired::dispatch($sub);

    expect(Notification::query()->where('user_id', $owner->id)->count())->toBe(2)
        ->and(Notification::query()->where('user_id', $other->id)->count())->toBe(0);
});
