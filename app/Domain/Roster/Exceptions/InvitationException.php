<?php

namespace App\Domain\Roster\Exceptions;

use RuntimeException;

/** Business-rule rejection from the invitation engine; `code` is the stable API error code (B5/B6 map it). */
final class InvitationException extends RuntimeException
{
    private function __construct(public readonly string $errorCode, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function alreadyPending(): self
    {
        return new self('invitation_already_pending', 'Bu email üçün aktiv dəvət artıq mövcuddur.', 409);
    }

    public static function notResendable(): self
    {
        return new self('invitation_not_resendable', 'Bu dəvət yenidən göndərilə bilməz.', 409);
    }

    public static function notRevocable(): self
    {
        return new self('invitation_not_revocable', 'Bu dəvət ləğv edilə bilməz.', 409);
    }

    public static function rateLimited(int $retryAfterSeconds): self
    {
        return new self('invitation_rate_limited', "Çox tez-tez göndərilir. {$retryAfterSeconds} saniyə sonra yenidən cəhd edin.", 429);
    }

    /** Unknown, expired, revoked, accepted or declined — ONE uniform answer (no enumeration, S-7). */
    public static function notClaimable(): self
    {
        return new self('invitation_invalid', 'Dəvət etibarsızdır və ya vaxtı bitib.', 410);
    }

    public static function emailMismatch(): self
    {
        return new self('invitation_email_mismatch', 'Bu dəvət başqa email ünvanı üçün göndərilib.', 403);
    }

    /** family_member acceptance lands with family links (IMPLEMENTATION_PLAN B8). */
    public static function kindUnsupported(): self
    {
        return new self('invitation_kind_unsupported', 'Bu dəvət növü hələ qəbul edilə bilməz.', 409);
    }

    /** The invitee already holds an active roster row on that device (B8). */
    public static function alreadyHasAccess(): self
    {
        return new self('invitation_already_has_access', 'Bu şəxsin həmin cihaza artıq girişi var.', 409);
    }

    public static function invalidTarget(): self
    {
        return new self('invitation_invalid_target', 'Dəvət hədəfi düzgün deyil.', 422);
    }
}
