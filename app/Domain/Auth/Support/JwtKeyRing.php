<?php

namespace App\Domain\Auth\Support;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use RuntimeException;

/**
 * Resolves the RS256 signing material per JWT audience (user / admin) and builds the
 * lcobucci Configuration (R-SEC-02/08). PEM content from env wins (production secret
 * store); otherwise the on-disk pair from `auth:generate-keys` is used. Configurations
 * are memoised per audience for the request lifetime.
 */
final class JwtKeyRing
{
    /** @var array<string, Configuration> */
    private array $configs = [];

    /**
     * lcobucci Configuration for the given audience ('user' | 'admin').
     */
    public function configFor(string $audience): Configuration
    {
        return $this->configs[$audience] ??= Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->privateKey($audience)),
            InMemory::plainText($this->publicKey($audience)),
        );
    }

    public function kid(string $audience): string
    {
        return (string) config("domain.auth.access.{$audience}.kid");
    }

    public function privateKey(string $audience): string
    {
        return $this->resolve(
            (string) config("domain.auth.keys.{$audience}.private"),
            (string) config("domain.auth.keys.{$audience}.private_path"),
            $audience,
        );
    }

    public function publicKey(string $audience): string
    {
        return $this->resolve(
            (string) config("domain.auth.keys.{$audience}.public"),
            (string) config("domain.auth.keys.{$audience}.public_path"),
            $audience,
        );
    }

    /**
     * JWKS entries for the admin verification key (CRIT-04 / R-SEC-08). A real rotation
     * would return both the current and previous keys; the keyring exposes the active one.
     *
     * @return array<int, array<string, string>>
     */
    public function adminJwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public($this->publicKey('admin')) ?: throw new RuntimeException('Invalid admin public key.'));

        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Could not extract admin RSA modulus/exponent.');
        }

        return [[
            'kty' => 'RSA',
            'kid' => $this->kid('admin'),
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]];
    }

    private function resolve(string $inline, string $path, string $audience): string
    {
        if (trim($inline) !== '') {
            return $inline;
        }

        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        throw new RuntimeException(
            "Missing {$audience} JWT key. Set AUTH_JWT_".strtoupper($audience)."_PRIVATE_KEY/_PUBLIC_KEY ".
            'or run `php artisan auth:generate-keys`.'
        );
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
