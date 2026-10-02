<?php

namespace App\Domain\Devices\Enums;

/**
 * How a device is owned (IMPLEMENTATION_PLAN §4.4). `private` (default — every existing device) keeps the
 * single-owner model unchanged; `complex` = a residential-complex device with no individual owner where each
 * resident holds an independent subscription (behaviour lands in B4; B2 only adds the column).
 */
enum DeviceOwnershipMode: string
{
    case Private = 'private';
    case Complex = 'complex';
}
