<?php

namespace App\Domain\Subscriptions\Enums;

/** What a special subscription price applies to. A person's price beats a device's, a device's beats a complex's. */
enum SubscriptionPriceScope: string
{
    case User = 'user';
    case Device = 'device';
    case Complex = 'complex';
}
