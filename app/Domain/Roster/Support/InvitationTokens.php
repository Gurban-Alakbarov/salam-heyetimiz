<?php

namespace App\Domain\Roster\Support;

/**
 * Invitation link secrets (IMPLEMENTATION_PLAN §9 / S-7). 32 random bytes, base64url; only the sha256 hash is
 * stored — the plaintext exists once, in the email link. Same pattern as visitor links.
 */
final class InvitationTokens
{
    /** @return array{plaintext:string, hash:string} */
    public function generate(): array
    {
        $plaintext = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return ['plaintext' => $plaintext, 'hash' => $this->hash($plaintext)];
    }

    public function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public function link(string $plaintext): string
    {
        return rtrim((string) config('domain.invitations.link_base'), '/').'/'.$plaintext;
    }
}
