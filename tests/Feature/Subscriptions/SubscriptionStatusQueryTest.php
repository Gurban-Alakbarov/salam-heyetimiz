<?php

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;

/*
| Tech Spec §13.9 device-access eligibility + per-caller suspension reason (CRIT-02).
*/

function statusFor(int $userId, int $deviceId)
{
    return app(SubscriptionStatusQuery::class)->for($userId, $deviceId);
}

it('grants access when the caller holds an active subscription', function () {
    $owner = makeUser('+994500000080');
    $device = makeActiveDevice('SER-Q1', '+994700000080');
    $du = makeDeviceUser($owner, $device, 'owner');
    makeSubscription($du); // active

    $result = statusFor($owner->id, $device->id);

    expect($result->canOpen)->toBeTrue()
        ->and($result->suspensionReason)->toBe(SuspensionReason::None);
});

it('denies access on a disabled device regardless of subscription', function () {
    $owner = makeUser('+994500000081');
    $device = makeActiveDevice('SER-Q2', '+994700000081');
    $device->update(['status' => 'disabled']);
    $du = makeDeviceUser($owner, $device, 'owner');
    makeSubscription($du);

    $result = statusFor($owner->id, $device->id);

    expect($result->canOpen)->toBeFalse()
        ->and($result->suspensionReason)->toBe(SuspensionReason::DeviceDisabled);
});

it('flags an owner whose sub lapsed while other users remain active', function () {
    $owner = makeUser('+994500000082');
    $other = makeUser('+994500000083');
    $device = makeActiveDevice('SER-Q3', '+994700000082');

    $ownerDu = makeDeviceUser($owner, $device, 'owner');
    makeSubscription($ownerDu, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $otherDu = makeDeviceUser($other, $device, 'user');
    makeSubscription($otherDu); // active

    $result = statusFor($owner->id, $device->id);

    expect($result->canOpen)->toBeFalse()
        ->and($result->suspensionReason)->toBe(SuspensionReason::OwnerSubExpiredOthersActive);
});

it('suspends the device for a lapsed caller when nobody else is active', function () {
    $user = makeUser('+994500000084');
    $device = makeActiveDevice('SER-Q4', '+994700000083');
    $du = makeDeviceUser($user, $device, 'user');
    makeSubscription($du, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $result = statusFor($user->id, $device->id);

    expect($result->canOpen)->toBeFalse()
        ->and($result->suspensionReason)->toBe(SuspensionReason::DeviceSuspended);
});

it('reports subscription_expired for a lapsed sub-user while others stay active', function () {
    $owner = makeUser('+994500000085');
    $subUser = makeUser('+994500000086');
    $device = makeActiveDevice('SER-Q5', '+994700000084');

    $ownerDu = makeDeviceUser($owner, $device, 'owner');
    makeSubscription($ownerDu); // active

    $subUserDu = makeDeviceUser($subUser, $device, 'user');
    makeSubscription($subUserDu, ['status' => SubscriptionStatus::Expired, 'ends_at' => now()->subDay()]);

    $result = statusFor($subUser->id, $device->id);

    expect($result->canOpen)->toBeFalse()
        ->and($result->suspensionReason)->toBe(SuspensionReason::SubscriptionExpired);
});

it('denies a caller with no roster relationship to the device', function () {
    $stranger = makeUser('+994500000087');
    $device = makeActiveDevice('SER-Q6', '+994700000085');

    $result = statusFor($stranger->id, $device->id);

    expect($result->canOpen)->toBeFalse()
        ->and($result->suspensionReason)->toBe(SuspensionReason::None);
});
