<?php

namespace App\Domain\Subscriptions\Enums;

/**
 * Per-caller derived device suspension reason (CRIT-02 / Tech Spec §13.9; openapi
 * Device.suspension_reason). Computed by SubscriptionStatusQuery — never a single shared
 * device-wide string. The most counter-intuitive case is owner_sub_expired_others_active.
 */
enum SuspensionReason: string
{
    case None = 'none';
    case SubscriptionExpired = 'subscription_expired';
    case OwnerSubExpiredOthersActive = 'owner_sub_expired_others_active';
    case DeviceDisabled = 'device_disabled';
    case DeviceSuspended = 'device_suspended';
}
