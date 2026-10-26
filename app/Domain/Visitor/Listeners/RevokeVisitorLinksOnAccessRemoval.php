<?php

namespace App\Domain\Visitor\Listeners;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Roster\Events\RosterUserRemoved;
use App\Domain\Visitor\Models\VisitorLink;
use App\Support\Time\Clock;

/**
 * K7 — a resident who loses access to a device (Komendant / family head / admin removal, account deletion)
 * takes their live visitor links on that device with them: they are revoked at once, so lists and the public
 * page say so. (Opening is refused regardless — VisitorAccessService checks the creator's access on every
 * attempt; this only makes the state visible.) System revocation: revoked_by_user/admin stay null.
 */
class RevokeVisitorLinksOnAccessRemoval
{
    public function __construct(
        private readonly Clock $clock,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(RosterUserRemoved $event): void
    {
        $now = $this->clock->now();

        $ids = VisitorLink::query()
            ->where('device_id', $event->device->getKey())
            ->where('created_by_user_id', $event->userId)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        VisitorLink::query()->whereKey($ids)->update(['revoked_at' => $now]);

        $this->audit->record('visitor_link.revoked_on_access_removal', [
            'device_id' => (int) $event->device->getKey(),
            'user_id' => $event->userId,
            'visitor_link_ids' => $ids->map(fn ($id): int => (int) $id)->all(),
        ]);
    }
}
