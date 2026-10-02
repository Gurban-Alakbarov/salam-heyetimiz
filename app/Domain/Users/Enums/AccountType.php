<?php

namespace App\Domain\Users\Enums;

/** B9 (BR-5): physical vs legal person. NULL on users = legacy account (unchanged behaviour). */
enum AccountType: string
{
    case Physical = 'physical';
    case Legal = 'legal';
}
