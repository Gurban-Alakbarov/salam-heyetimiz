<?php

namespace App\Domain\Roster\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\ResidentArchive;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Carbon\CarbonImmutable;

/**
 * O10 — copy an account an admin is deleting into resident_archives BEFORE DeleteResident takes its access away
 * and anonymises it: the personal data, plus a snapshot of the devices it was on (role, complex, family grant,
 * latest subscription), its complexes and its family. Kept privacy.resident_archive_years, then purged.
 */
final class ResidentArchiver
{
    public function __construct(
        private readonly ComplexMembershipService $memberships,
        private readonly Clock $clock,
    ) {}

    public function archive(User $user, ?AdminUser $admin, string $reason = ResidentArchive::REASON_ADMIN_DELETED): ResidentArchive
    {
        $now = CarbonImmutable::instance($this->clock->now());

        return ResidentArchive::query()->create([
            'user_id' => (int) $user->getKey(),
            'phone' => $user->phone,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'account_type' => $user->account_type?->value,
            'email_verified_at' => $user->email_verified_at,
            'registered_at' => $user->created_at,
            'last_login_at' => $user->last_login_at,
            'deleted_by_admin_id' => $admin?->getKey(),
            'reason' => $reason,
            'snapshot' => $this->snapshot($user),
            'archived_at' => $now,
            'purge_after' => $now->addYears((int) config('domain.privacy.resident_archive_years')),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        $userId = (int) $user->getKey();

        $rows = DeviceUser::query()->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->get();
        $devices = Device::query()->whereIn('id', $rows->pluck('device_id'))->get()->keyBy('id');
        $complexIds = array_values(array_unique(array_merge(
            $this->memberships->complexIdsFor($userId),
            $devices->pluck('complex_id')->filter()->map(fn ($id): int => (int) $id)->all(),
        )));
        $complexes = Complex::query()->whereIn('id', $complexIds)->pluck('name', 'id');

        return [
            'devices' => $rows->map(function (DeviceUser $row) use ($devices, $complexes): array {
                $device = $devices->get($row->device_id);
                /** @var Subscription|null $sub */
                $sub = Subscription::query()->where('device_user_id', $row->getKey())->orderByDesc('id')->first();

                return [
                    'device_id' => (int) $row->device_id,
                    'serial' => $device?->serial,
                    'label' => $device?->location_label,
                    'ownership_mode' => $device?->ownership_mode?->value,
                    'complex' => $device?->complex_id !== null
                        ? ['id' => (int) $device->complex_id, 'name' => $complexes->get($device->complex_id)]
                        : null,
                    'role' => $row->role->value,
                    'via_family' => $row->family_link_id !== null,
                    'since' => $row->created_at?->toIso8601String(),
                    'subscription' => $sub !== null ? [
                        'id' => (int) $sub->getKey(),
                        'tier' => $sub->tier->value,
                        'status' => $sub->status->value,
                        'starts_at' => $sub->starts_at?->toIso8601String(),
                        'ends_at' => $sub->ends_at?->toIso8601String(),
                    ] : null,
                ];
            })->values()->all(),
            'complexes' => collect($this->memberships->complexIdsFor($userId))
                ->map(fn (int $id): array => ['id' => $id, 'name' => $complexes->get($id)])->all(),
            'family' => [
                'head_of_member_ids' => FamilyLink::query()->where('head_user_id', $userId)
                    ->where('status', FamilyLinkStatus::Active->value)->pluck('member_user_id')->map(fn ($id): int => (int) $id)->all(),
                'member_of_head_ids' => FamilyLink::query()->where('member_user_id', $userId)
                    ->where('status', FamilyLinkStatus::Active->value)->pluck('head_user_id')->map(fn ($id): int => (int) $id)->all(),
            ],
        ];
    }
}
