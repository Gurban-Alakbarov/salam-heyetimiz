<?php

namespace App\Domain\Roster\Jobs;

use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Support\InvitationTokens;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends an invitation email via the existing TemplatedMailer (Brevo SMTP). The payload carries the one-time
 * plaintext token, so the job is ENCRYPTED at rest in the queue. A token that is no longer the invitation's
 * current one (rotated by a resend) or a non-live invitation is skipped — never sends a dead link. Mail not
 * configured → logged, invitation kept (the inviter can resend).
 */
class SendInvitationEmailJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $invitationId, public readonly string $token)
    {
        $this->onQueue('notifications');
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(TemplatedMailer $mailer, InvitationTokens $tokens, Clock $clock): void
    {
        $invitation = Invitation::query()->with(['complex', 'invitedBy'])->find($this->invitationId);
        if ($invitation === null
            || ! $invitation->isLive($clock->now())
            || ! hash_equals((string) $invitation->token_hash, $tokens->hash($this->token))) {
            return; // revoked / accepted / expired / superseded by a resend
        }

        if (! $mailer->isConfigured()) {
            Log::warning('invitation.email_not_sent', ['invitation_id' => $invitation->id, 'reason' => 'mailer_not_configured']);

            return;
        }

        $mailer->send($this->type($invitation), (string) $invitation->invitee_email, self::emailData($invitation, $tokens->link($this->token)), 'az');
    }

    private function type(Invitation $invitation): EmailType
    {
        return $invitation->kind === InvitationKind::ComplexResident ? EmailType::ResidentInvitation : EmailType::FamilyInvitation;
    }

    /** @return array<string, mixed> */
    public static function emailData(Invitation $invitation, string $link): array
    {
        $inviter = $invitation->kind === InvitationKind::ComplexResident
            ? (string) ($invitation->complex?->name ?? 'Yaşayış kompleksi')
            : (string) ($invitation->invitedBy?->full_name ?? 'Ailə üzvünüz');
        $expires = $invitation->expires_at->setTimezone('Asia/Baku')->format('d.m.Y H:i');

        return [
            'firstName' => (string) $invitation->invitee_first_name,
            'inviter' => $inviter,
            'link' => $link,
            'expiresAt' => $expires,
            'lines' => [
                'Salam, '.$invitation->invitee_first_name.'!',
                $invitation->kind === InvitationKind::ComplexResident
                    ? $inviter.' sizi Salam Həyətimiz tətbiqinə sakin kimi dəvət edir.'
                    : $inviter.' sizi Salam Həyətimiz tətbiqində ailə üzvü kimi dəvət edir.',
                'Dəvəti qəbul etmək üçün: '.$link,
                'Link '.$expires.' tarixinədək etibarlıdır.',
                'Bu dəvəti gözləmirdinizsə, məktubu nəzərə almayın.',
            ],
        ];
    }
}
