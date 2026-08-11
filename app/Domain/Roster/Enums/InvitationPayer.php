<?php

namespace App\Domain\Roster\Enums;

/**
 * Who pays the additional-user subscription (Tech Spec §13; DB Arch §3.4).
 */
enum InvitationPayer: string
{
    case Owner = 'owner';
    case Invitee = 'invitee';
}
