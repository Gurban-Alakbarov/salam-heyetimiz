<?php

namespace App\Domain\DeviceComm\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * The caller may not open this device right now (R-DOM-05). 403 with the per-caller reason mapped to
 * the standard codes (openapi openDevice 403: subscription_required / device_disabled / forbidden).
 */
class OpenNotPermittedException extends DomainException
{
    /**
     * @param  array<string, mixed>|null  $detail
     */
    private function __construct(private readonly string $errorCode, private readonly ?array $detail = null)
    {
        parent::__construct('Open not permitted.');
    }

    public static function subscriptionRequired(int $deviceId): self
    {
        return new self('subscription_required', ['device_id' => $deviceId]);
    }

    public static function deviceDisabled(int $deviceId): self
    {
        return new self('device_disabled', ['device_id' => $deviceId]);
    }

    public static function forbidden(): self
    {
        return new self('forbidden');
    }

    public function httpStatus(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function messageKey(): string
    {
        return "errors.{$this->errorCode}";
    }

    public function details(): ?array
    {
        return $this->detail;
    }
}
