<?php

namespace App\Domain\Roster\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An admin removed a resident from the SYSTEM (account soft-deleted, every roster membership revoked,
 * entitlements cancelled, sessions killed). Per-device whitelist removal is already driven by the
 * RosterUserRemoved events emitted during the revoke — this event exists for the audit trail.
 */
class ResidentDeleted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly ?int $adminId,
        public readonly int $revokedMemberships,
        public readonly int $cancelledSubscriptions,
        public readonly bool $selfService = false,
        /** @var array{phone_sha256: ?string, email_sha256: ?string} — Y7: only hashes of the erased identifiers */
        public readonly array $erasedHashes = ['phone_sha256' => null, 'email_sha256' => null],
    ) {}

    public function auditAction(): string
    {
        return 'resident.deleted';
    }

    public function auditPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'deleted_by_admin_id' => $this->adminId,
            'revoked_memberships' => $this->revokedMemberships,
            'cancelled_subscriptions' => $this->cancelledSubscriptions,
            'soft_deleted' => true,
            'self_service' => $this->selfService,
            'anonymised' => true,
            // sha256 of the original phone / email — lets support match a ghost without keeping the data.
            'phone_sha256' => $this->erasedHashes['phone_sha256'] ?? null,
            'email_sha256' => $this->erasedHashes['email_sha256'] ?? null,
        ];
    }
}
