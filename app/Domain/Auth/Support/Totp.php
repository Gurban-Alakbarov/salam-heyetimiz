<?php

namespace App\Domain\Auth\Support;

/**
 * RFC 6238 TOTP / RFC 4226 HOTP (R-SEC-05). Pure and stateless: the secret is a
 * base32 string (encrypted at rest in admin_users.totp_secret). A ±`window` step
 * tolerance absorbs client/server clock drift. No external dependency.
 */
final class Totp
{
    public function __construct(
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly string $algorithm = 'sha1',
        private readonly int $window = 1,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            period: (int) config('domain.auth.totp.period', 30),
            digits: (int) config('domain.auth.totp.digits', 6),
            algorithm: (string) config('domain.auth.totp.algorithm', 'sha1'),
            window: (int) config('domain.auth.totp.window', 1),
        );
    }

    /** A new random base32 secret (default 160 bits — RFC 4226 recommendation). */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** Verify a candidate code against the secret within the drift window. */
    public function verify(string $secretBase32, string $code, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{'.$this->digits.'}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), $this->period);

        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            if (hash_equals($this->hotp($secretBase32, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** The code valid at the given instant (now if null) — used by tests/clients. */
    public function at(string $secretBase32, ?int $timestamp = null): string
    {
        return $this->hotp($secretBase32, intdiv($timestamp ?? time(), $this->period));
    }

    private function hotp(string $secretBase32, int $counter): string
    {
        $key = $this->base32Decode($secretBase32);
        $binCounter = pack('N*', 0).pack('N*', $counter); // 8-byte big-endian
        $hash = hash_hmac($this->algorithm, $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $output .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $output;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue; // ignore non-alphabet characters (e.g. spaces)
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
