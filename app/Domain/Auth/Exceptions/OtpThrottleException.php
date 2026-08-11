<?php

namespace App\Domain\Auth\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * OTP request throttled by the admin-configured daily/hourly limit or an active temporary block → 429.
 * Default limits are 0 (unlimited), so this never fires unless an operator opts in via Settings → OTP.
 */
class OtpThrottleException extends DomainException
{
    private function __construct(private readonly string $errorCode, private readonly int $retryAfter)
    {
        parent::__construct('OTP request throttled.');
    }

    public static function rateLimited(int $retryAfter = 3600): self
    {
        return new self('otp_rate_limited', $retryAfter);
    }

    public static function blocked(int $retryAfter = 3600): self
    {
        return new self('otp_temporarily_blocked', $retryAfter);
    }

    public function httpStatus(): int
    {
        return 429;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return "errors.{$this->errorCode}";
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['Retry-After' => (string) $this->retryAfter];
    }
}
