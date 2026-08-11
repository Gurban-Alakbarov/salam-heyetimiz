<?php

namespace App\Domain\Auth\Enums;

enum AuthOutcome: string
{
    case Success = 'success';
    case WrongCredential = 'wrong_credential';
    case Locked = 'locked';
    case RateLimited = 'rate_limited';
    case OtpExpired = 'otp_expired';
    case OtpMaxAttempts = 'otp_max_attempts';
    case TwoFactorFailed = '2fa_failed';
}
