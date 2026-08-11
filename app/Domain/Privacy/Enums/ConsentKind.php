<?php

namespace App\Domain\Privacy\Enums;

enum ConsentKind: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case MarketingPush = 'marketing_push';
    case MarketingSms = 'marketing_sms';
    case DataProcessing = 'data_processing';
}
