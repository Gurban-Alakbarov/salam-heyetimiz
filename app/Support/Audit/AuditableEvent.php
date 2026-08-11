<?php

namespace App\Support\Audit;

/**
 * Domain events implement this so the generic audit listener (RecordAuditFromEvent, batch 09)
 * can record them without per-event code (BACKEND §8.1).
 */
interface AuditableEvent
{
    /** Dotted audit action key, e.g. `order.paid`. */
    public function auditAction(): string;

    /**
     * Redaction-safe payload for the audit_log entry.
     *
     * @return array<string, mixed>
     */
    public function auditPayload(): array;
}
