<?php

namespace App\Domain\Subscriptions\Support;

use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Who may PAY for a subscription (IMPLEMENTATION_PLAN §4.3 / B7, BR-3). The payer is recorded separately
 * (orders.payer_user_id → subscription_periods.paid_by_user_id); the beneficiary never changes.
 *
 *  - the beneficiary themself;
 *  - the family head who granted that row (`family_link_id` set, `added_by_user_id` = payer) while the head
 *    still holds an active row on the same device and the family link (B8) is still active;
 *  - on a PRIVATE device, its owner (the household head of the existing single-owner model — unchanged).
 *    Complex devices have no owner: there only the beneficiary or their family head may pay.
 *
 * Never: another resident, another family member, a Komendant or an admin acting as a mobile payer.
 */
final class SubscriptionPaymentAuthorizer
{
    public function canPay(User $payer, Subscription $subscription): bool
    {
        /** @var DeviceUser|null $row */
        $row = DeviceUser::query()->with('device')->find($subscription->device_user_id);
        if ($row === null) {
            return false;
        }

        $payerId = (int) $payer->getKey();
        if ((int) $row->user_id === $payerId) {
            return true;
        }

        // B8: nobody pays on someone else's behalf for access that has been revoked (removed family member).
        if ($row->status !== DeviceUserStatus::Active) {
            return false;
        }

        // B8: the head of the ACTIVE family link this row was granted through (link removed → no longer payer).
        if ($row->family_link_id !== null && (int) $row->added_by_user_id === $payerId
            && FamilyLink::query()->whereKey($row->family_link_id)->where('status', FamilyLinkStatus::Active->value)
                ->where('head_user_id', $payerId)->where('member_user_id', $row->user_id)->exists()
            && DeviceUser::query()->where('device_id', $row->device_id)->where('user_id', $payerId)
                ->where('status', DeviceUserStatus::Active->value)->exists()) {
            return true;
        }

        if ($row->device === null || $row->device->ownership_mode !== DeviceOwnershipMode::Private) {
            return false;
        }

        // Private device owner: devices.owner_user_id or the active roster `owner` row (same person in practice).
        return (int) $row->device->owner_user_id === $payerId
            || DeviceUser::query()->where('device_id', $row->device_id)->where('user_id', $payerId)
                ->where('role', DeviceUserRole::Owner->value)->where('status', DeviceUserStatus::Active->value)->exists();
    }

    /** @throws AuthorizationException */
    public function assertCanPay(User $payer, Subscription $subscription): void
    {
        if (! $this->canPay($payer, $subscription)) {
            throw new AuthorizationException('Bu abunəliyin ödənişini etmək icazəniz yoxdur.');
        }
    }
}
