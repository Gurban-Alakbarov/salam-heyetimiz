<?php

namespace App\Domain\Payments\Enums;

enum OrderItemType: string
{
    case Device = 'device';
    case SubMain = 'sub_main';
    case SubAdditional = 'sub_additional';
    case SubRenewal = 'sub_renewal';
}
