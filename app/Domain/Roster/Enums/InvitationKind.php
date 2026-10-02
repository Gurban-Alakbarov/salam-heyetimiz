<?php

namespace App\Domain\Roster\Enums;

/** What an invitation grants on acceptance (IMPLEMENTATION_PLAN §9). One engine, two kinds. */
enum InvitationKind: string
{
    /** A family head invites a member onto devices they hold (claimed in B8). */
    case FamilyMember = 'family_member';

    /** A Komendant invites a resident into their residential complex (claimed in B6). */
    case ComplexResident = 'complex_resident';
}
