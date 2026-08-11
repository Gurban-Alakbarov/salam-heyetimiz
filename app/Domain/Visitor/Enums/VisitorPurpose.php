<?php

namespace App\Domain\Visitor\Enums;

/**
 * Optional, self-declared reason a visitor link was created — purely for future reporting/analytics
 * (never affects access). Stored as a plain string column so new purposes are a one-line enum change with
 * no migration; input is validated against these cases, so persisted values always map back cleanly.
 */
enum VisitorPurpose: string
{
    case Guest = 'guest';
    case Delivery = 'delivery';
    case Courier = 'courier';
    case Service = 'service';       // repairs, maintenance, installations
    case Cleaning = 'cleaning';     // carpet cleaning, housekeeping
    case Taxi = 'taxi';
    case Other = 'other';
}
