<?php

namespace App\Http\Api\V1\Controllers\Invitations;

use App\Domain\Roster\Actions\ClaimInvitation;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\InvitationService;
use App\Http\Api\V1\Support\RespondsWithEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invitation lookup / accept / decline (IMPLEMENTATION_PLAN §9 / B6). The path token is the credential;
 * every non-live token (unknown, expired, revoked, accepted, declined) gets the SAME 410 envelope.
 * New users claim through register → verify-email (`invitation_token`); existing users call accept.
 */
class InviteController
{
    use RespondsWithEnvelope;

    public function __construct(
        private readonly InvitationService $invitations,
        private readonly ClaimInvitation $claim,
    ) {}

    /** GET /v1/invites/{token} — public, throttled. */
    public function show(string $token): JsonResponse
    {
        $invitation = $this->invitations->findLive($token);
        if ($invitation === null) {
            return $this->fail(InvitationException::notClaimable());
        }

        return $this->success(self::publicView($invitation), 'Dəvət etibarlıdır.');
    }

    /** POST /v1/invites/{token}/accept — the signed-in invitee (verified email must match). */
    public function accept(Request $request, string $token): JsonResponse
    {
        try {
            $result = $this->claim->handle($this->live($token), $request->user());
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->success($result, 'Dəvət qəbul edildi.');
    }

    /** POST /v1/invites/{token}/decline */
    public function decline(Request $request, string $token): JsonResponse
    {
        try {
            $this->claim->decline($this->live($token), $request->user());
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->success(null, 'Dəvət rədd edildi.');
    }

    /**
     * What the invitee may see before signing in — no ids, the email masked.
     *
     * @return array<string, mixed>
     */
    public static function publicView(Invitation $invitation): array
    {
        $isComplex = $invitation->kind === InvitationKind::ComplexResident;

        return [
            'kind' => $invitation->kind->value,
            'complex_name' => $isComplex ? $invitation->complex?->name : null,
            'inviter_name' => $isComplex ? $invitation->complex?->name : $invitation->invitedBy?->full_name,
            'first_name' => $invitation->invitee_first_name,
            'last_name' => $invitation->invitee_last_name,
            'email_masked' => self::maskEmail((string) $invitation->invitee_email),
            'status' => 'pending',
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 1)).'@'.$domain;
    }

    private function live(string $token): Invitation
    {
        return $this->invitations->findLive($token) ?? throw InvitationException::notClaimable();
    }

    private function fail(InvitationException $e): JsonResponse
    {
        return $this->failure($e->getMessage(), ['code' => $e->errorCode], $e->status);
    }
}
