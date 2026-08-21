<?php

use App\Domain\Notifications\Enums\CampaignStatus;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;

/*
| Notification domain enums (DB Arch §7). Pure classification — no database.
*/

it('maps channels to their default_channels_mask bits', function () {
    expect(NotificationChannel::Push->bit())->toBe(1)
        ->and(NotificationChannel::Sms->bit())->toBe(2)
        ->and(NotificationChannel::Inapp->bit())->toBe(4)
        ->and(NotificationChannel::Email->bit())->toBe(8);
});

it('decodes a channels mask in stable enum order', function () {
    // push (1) + inapp (4) = 5 — the MVP mask.
    expect(NotificationChannel::fromMask(5))->toBe([NotificationChannel::Push, NotificationChannel::Inapp])
        ->and(NotificationChannel::fromMask(0))->toBe([])
        ->and(NotificationChannel::fromMask(15))->toBe([
            NotificationChannel::Push,
            NotificationChannel::Sms,
            NotificationChannel::Inapp,
            NotificationChannel::Email,
        ]);
});

it('classifies delivered notification statuses', function (NotificationStatus $status, bool $delivered) {
    expect($status->isDelivered())->toBe($delivered);
})->with([
    'queued' => [NotificationStatus::Queued, false],
    'sent' => [NotificationStatus::Sent, true],
    'failed' => [NotificationStatus::Failed, false],
    'read' => [NotificationStatus::Read, true],
]);

it('treats only marketing as user-mutable by default', function (NotificationCategory $category, bool $mutable) {
    expect($category->defaultUserMutable())->toBe($mutable);
})->with([
    'security' => [NotificationCategory::Security, false],
    'billing' => [NotificationCategory::Billing, false],
    'operational' => [NotificationCategory::Operational, false],
    'marketing' => [NotificationCategory::Marketing, true],
]);

it('allows editing only a draft campaign and classifies terminal states', function () {
    expect(CampaignStatus::Draft->isEditable())->toBeTrue()
        ->and(CampaignStatus::Queued->isEditable())->toBeFalse()
        ->and(CampaignStatus::Sent->isEditable())->toBeFalse()
        ->and(CampaignStatus::Sending->isTerminal())->toBeFalse()
        ->and(CampaignStatus::Sent->isTerminal())->toBeTrue()
        ->and(CampaignStatus::Failed->isTerminal())->toBeTrue();
});
