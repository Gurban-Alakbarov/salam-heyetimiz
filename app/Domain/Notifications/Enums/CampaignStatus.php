<?php

namespace App\Domain\Notifications\Enums;

/**
 * Admin notification campaign lifecycle (DB Arch §7.5; Tech Spec §17.6). `draft` is edited/confirmed;
 * confirmation moves it to `queued`; fan-out runs it through `sending` to a terminal `sent`/`failed`.
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    /** Only a draft campaign may still be edited or deleted (before confirmation). */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::Failed], true);
    }
}
