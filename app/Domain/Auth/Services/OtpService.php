<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Contracts\EmailOtpTransport;
use App\Domain\Auth\Contracts\OtpTransport;
use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Exceptions\OtpVerificationException;
use App\Domain\Auth\Models\Otp;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * One-time codes (R-SEC-03): TTL + length + max-attempts + resend all read from Settings via
 * config('domain.auth.otp.*'), hashed at rest (SHA-256), never logged. Requesting a code supersedes
 * any prior unconsumed code for the (destination, purpose) pair. Verification is single-use and
 * increments the attempt counter under a row lock so concurrent guesses cannot exceed the cap.
 *
 * Two delivery channels share one engine: `issue/verify` go to a phone over SMS (unchanged); the
 * additive `issueToEmail/verifyByEmail` go to an email over the generic templated mailer. The phone
 * path is byte-for-byte the original; both converge on the private store()/consume() helpers so the
 * logic is not duplicated.
 */
final class OtpService
{
    public function __construct(
        private readonly Clock $clock,
        private readonly OtpTransport $transport,
        private readonly OtpThrottle $throttle,
        private readonly EmailOtpTransport $emailTransport,
    ) {}

    /**
     * Issue + dispatch a phone code (SMS). Response is identical regardless of account existence
     * (anti-enumeration — openapi requestOtp 202).
     *
     * @return array{expires_in_seconds: int, resend_available_in_seconds: int}
     */
    public function issue(string $phone, OtpPurpose $purpose, string $ip, string $locale): array
    {
        $this->throttle->register($phone); // admin-configured daily/hourly caps (default unlimited)

        $code = $this->store('phone', $phone, 'sms', $purpose, $ip);
        $this->transport->send($phone, $code, $locale);

        return $this->timing();
    }

    /**
     * Issue + dispatch an email code (registration / email login). Reuses the same engine + Settings
     * tunables as the phone path; delivery goes through the generic templated mailer.
     *
     * @return array{expires_in_seconds: int, resend_available_in_seconds: int}
     */
    public function issueToEmail(string $email, OtpPurpose $purpose, string $ip, string $locale): array
    {
        $this->throttle->register($email);

        $code = $this->store('email', $email, 'email', $purpose, $ip);
        $this->emailTransport->send($email, $code, $locale, $purpose);

        return $this->timing();
    }

    /**
     * Verify a phone code. Consumes it on success; throws the precise 401 cause otherwise. `purpose`
     * is optional — the mobile verify endpoint carries no purpose, so it matches the latest code.
     */
    public function verify(string $phone, string $code, ?OtpPurpose $purpose = null): void
    {
        $this->consume('phone', $phone, $code, $purpose);
    }

    /** Verify an email code (registration / email login). Same semantics as {@see verify()}. */
    public function verifyByEmail(string $email, string $code, ?OtpPurpose $purpose = null): void
    {
        $this->consume('email', $email, $code, $purpose);
    }

    /**
     * Generate + persist a code for a destination, superseding prior unconsumed codes for the
     * (destination, purpose) pair. Returns the cleartext code for the caller's transport.
     */
    private function store(string $column, string $destination, string $channel, OtpPurpose $purpose, string $ip): string
    {
        $ttl = (int) config('domain.auth.otp.ttl_seconds');
        $now = $this->clock->now();
        $code = $this->generateCode((int) config('domain.auth.otp.length'));

        DB::transaction(function () use ($column, $destination, $channel, $purpose, $now, $ttl, $code, $ip): void {
            // Supersede prior unconsumed codes for this (destination, purpose). The destination column
            // (phone XOR email) isolates channels — phone rows have email NULL and vice versa.
            Otp::query()
                ->where($column, $destination)
                ->where('purpose', $purpose->value)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now]);

            Otp::query()->create([
                $column => $destination,
                'channel' => $channel,
                'code_hash' => $this->hash($code),
                'purpose' => $purpose,
                'attempts' => 0,
                'max_attempts' => (int) config('domain.auth.otp.max_attempts'),
                'expires_at' => $now->addSeconds($ttl),
                'issued_ip' => $ip,
                'created_at' => $now,
            ]);
        });

        return $code;
    }

    /** Single-use verification under a row lock; raises the precise exception on failure. */
    private function consume(string $column, string $destination, string $code, ?OtpPurpose $purpose): void
    {
        $now = $this->clock->now();

        // The closure RETURNS the outcome (never throws) so a failed attempt's counter increment
        // commits; the precise exception is raised afterwards, outside the transaction.
        $outcome = DB::transaction(function () use ($column, $destination, $code, $purpose, $now): string {
            /** @var Otp|null $otp */
            $otp = Otp::query()
                ->where($column, $destination)
                ->when($purpose !== null, fn ($q) => $q->where('purpose', $purpose->value))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->consumed_at !== null) {
                return 'wrong';
            }

            if ($otp->expires_at->lessThanOrEqualTo($now)) {
                return 'expired';
            }

            if ($otp->attempts >= $otp->max_attempts) {
                return 'max';
            }

            if (! hash_equals($otp->code_hash, $this->hash($code))) {
                $otp->increment('attempts');

                return $otp->attempts >= $otp->max_attempts ? 'max' : 'wrong';
            }

            $otp->forceFill(['consumed_at' => $now])->save();

            return 'ok';
        });

        match ($outcome) {
            'ok' => null,
            'expired' => throw OtpVerificationException::expired(),
            'max' => throw OtpVerificationException::maxAttempts(),
            default => throw OtpVerificationException::wrongCode(),
        };
    }

    /** @return array{expires_in_seconds: int, resend_available_in_seconds: int} */
    private function timing(): array
    {
        return [
            'expires_in_seconds' => (int) config('domain.auth.otp.ttl_seconds'),
            'resend_available_in_seconds' => (int) config('domain.auth.otp.resend_seconds'),
        ];
    }

    private function generateCode(int $length): string
    {
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
