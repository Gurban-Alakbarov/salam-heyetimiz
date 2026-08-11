<?php

namespace App\Domain\Payments\Enums;

enum OrderPurpose: string
{
    case DeviceSale = 'device_sale';
    case SubMain = 'sub_main';
    case SubAdditional = 'sub_additional';
    case SubRenewal = 'sub_renewal';
    case Bundle = 'bundle';

    /** Purposes that activate/extend a subscription on payment (handled by the Subscriptions module). */
    public function fundsSubscription(): bool
    {
        return in_array($this, [self::SubMain, self::SubAdditional, self::SubRenewal], true);
    }
}
