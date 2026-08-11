<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Support\IssuedAccessToken;
use App\Domain\Auth\Support\JwtKeyRing;
use App\Support\Time\Clock;
use DateTimeImmutable;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * Mints and verifies the RS256 JWTs (R-SEC-02/05/08):
 *   - mobile access token   — sub=user.{id},  kind=user,  fp=<install_uuid>     (15 min)
 *   - admin access token     — sub=admin.{id}, kind=admin, tfa_verified=true     (30 min)
 *   - admin 2FA challenge     — sub=admin.{id}, kind=admin_challenge             (5 min)
 * Every token carries a `kid` header; admin verification keys are published via JWKS.
 */
final class JwtService
{
    private const CHALLENGE_PURPOSE = 'admin_2fa_challenge';

    public function __construct(
        private readonly JwtKeyRing $keys,
        private readonly Clock $clock,
    ) {}

    public function issueUserAccessToken(int $userId, string $fingerprint): IssuedAccessToken
    {
        $ttl = (int) config('domain.auth.access.user.ttl_seconds');
        $token = $this->build('user', "user.{$userId}", $ttl, [
            'kind' => 'user',
            'fp' => $fingerprint,
        ]);

        return new IssuedAccessToken($token, $ttl);
    }

    public function issueAdminAccessToken(int $adminId, string $role, ?int $impersonatorAdminId = null): IssuedAccessToken
    {
        $ttl = (int) config('domain.auth.access.admin.ttl_seconds');
        $claims = [
            'kind' => 'admin',
            'role' => $role,
            'tfa_verified' => true,
        ];
        if ($impersonatorAdminId !== null) {
            $claims['impersonator'] = $impersonatorAdminId; // super_admin acting as this admin
        }
        $token = $this->build('admin', "admin.{$adminId}", $ttl, $claims);

        return new IssuedAccessToken($token, $ttl);
    }

    public function issueAdminChallengeToken(int $adminId): string
    {
        $ttl = (int) config('domain.auth.challenge.ttl_seconds');

        return $this->build('admin', "admin.{$adminId}", $ttl, [
            'kind' => 'admin_challenge',
            'purpose' => self::CHALLENGE_PURPOSE,
        ]);
    }

    /**
     * Validate a mobile access token. Returns ['user_id'=>int,'fp'=>?string] or null.
     *
     * @return array{user_id: int, fp: ?string}|null
     */
    public function parseUserClaims(string $jwt): ?array
    {
        $token = $this->verify('user', $jwt);
        if ($token === null || $token->claims()->get('kind') !== 'user') {
            return null;
        }

        $id = $this->subjectId($token, 'user.');

        return $id === null ? null : ['user_id' => $id, 'fp' => $this->stringClaim($token, 'fp')];
    }

    /**
     * Validate an admin access token. Returns ['admin_id'=>int,'role'=>?string,'tfa_verified'=>bool] or null.
     *
     * @return array{admin_id: int, role: ?string, tfa_verified: bool, impersonator: ?int, issued_at: int}|null
     */
    public function parseAdminClaims(string $jwt): ?array
    {
        $token = $this->verify('admin', $jwt);
        if ($token === null || $token->claims()->get('kind') !== 'admin') {
            return null;
        }

        $id = $this->subjectId($token, 'admin.');
        $impersonator = $token->claims()->get('impersonator');
        $iat = $token->claims()->get(RegisteredClaims::ISSUED_AT);

        return $id === null ? null : [
            'admin_id' => $id,
            'role' => $this->stringClaim($token, 'role'),
            'tfa_verified' => (bool) $token->claims()->get('tfa_verified', false),
            'impersonator' => is_numeric($impersonator) ? (int) $impersonator : null,
            'issued_at' => $iat instanceof \DateTimeInterface ? $iat->getTimestamp() : 0,
        ];
    }

    /** Validate an admin 2FA challenge token and return the admin id, or null. */
    public function readChallengeAdminId(string $jwt): ?int
    {
        $token = $this->verify('admin', $jwt);
        if ($token === null
            || $token->claims()->get('kind') !== 'admin_challenge'
            || $token->claims()->get('purpose') !== self::CHALLENGE_PURPOSE) {
            return null;
        }

        return $this->subjectId($token, 'admin.');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function build(string $audience, string $subject, int $ttl, array $claims): string
    {
        $config = $this->keys->configFor($audience);
        $now = $this->clock->now();

        $builder = $config->builder()
            ->issuedBy((string) config('domain.auth.issuer'))
            ->permittedFor((string) config("domain.auth.access.{$audience}.audience"))
            ->relatedTo($subject)
            ->identifiedBy(bin2hex(random_bytes(16)))
            ->issuedAt($now->toDateTimeImmutable())
            ->expiresAt($now->addSeconds($ttl)->toDateTimeImmutable())
            ->withHeader('kid', $this->keys->kid($audience));

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    private function verify(string $audience, string $jwt): ?UnencryptedToken
    {
        try {
            $config = $this->keys->configFor($audience);
            $token = $config->parser()->parse($jwt);

            if (! $token instanceof UnencryptedToken) {
                return null;
            }

            $config->validator()->assert(
                $token,
                new SignedWith($config->signer(), $config->verificationKey()),
                new IssuedBy((string) config('domain.auth.issuer')),
                new PermittedFor((string) config("domain.auth.access.{$audience}.audience")),
                new LooseValidAt($this->psrClock()),
            );

            return $token;
        } catch (Throwable) {
            return null;
        }
    }

    private function subjectId(UnencryptedToken $token, string $prefix): ?int
    {
        $subject = (string) $token->claims()->get(RegisteredClaims::SUBJECT, '');

        if (! str_starts_with($subject, $prefix)) {
            return null;
        }

        $id = (int) substr($subject, strlen($prefix));

        return $id > 0 ? $id : null;
    }

    private function stringClaim(UnencryptedToken $token, string $name): ?string
    {
        $value = $token->claims()->get($name);

        return is_string($value) ? $value : null;
    }

    private function psrClock(): ClockInterface
    {
        $clock = $this->clock;

        return new class($clock) implements ClockInterface {
            public function __construct(private readonly Clock $clock) {}

            public function now(): DateTimeImmutable
            {
                return $this->clock->now()->toDateTimeImmutable();
            }
        };
    }
}
