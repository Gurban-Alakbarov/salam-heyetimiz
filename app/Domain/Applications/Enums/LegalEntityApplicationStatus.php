<?php

namespace App\Domain\Applications\Enums;

/** Legal-entity application lifecycle (IMPLEMENTATION_PLAN §11): pending → approved | rejected (terminal). */
enum LegalEntityApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
