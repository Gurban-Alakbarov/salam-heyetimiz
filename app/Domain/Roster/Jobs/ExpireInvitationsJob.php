<?php

namespace App\Domain\Roster\Jobs;

use App\Domain\Roster\Services\InvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Hourly sweep: pending invitations past their 7-day window → expired (IMPLEMENTATION_PLAN §9.9). */
class ExpireInvitationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(InvitationService $invitations): int
    {
        return $invitations->expireDue();
    }
}
