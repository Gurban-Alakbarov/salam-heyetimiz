<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Events\OtpRequested;
use App\Domain\Auth\Services\OtpService;
use App\Support\Phone\PhoneNumber;

/**
 * Issue + dispatch an OTP (openapi requestOtp). The response is identical whether or not
 * an account exists for the phone (anti-enumeration). Only a masked phone is audited.
 */
final class SendOtp
{
    public function __construct(private readonly OtpService $otp) {}

    /**
     * @return array{expires_in_seconds: int, resend_available_in_seconds: int}
     */
    public function handle(string $phone, OtpPurpose $purpose, string $ip, string $locale): array
    {
        $result = $this->otp->issue($phone, $purpose, $ip, $locale);

        OtpRequested::dispatch(PhoneNumber::fromInput($phone)->masked(), $purpose->value);

        return $result;
    }
}
