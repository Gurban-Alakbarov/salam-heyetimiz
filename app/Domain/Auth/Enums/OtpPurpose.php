<?php

namespace App\Domain\Auth\Enums;

enum OtpPurpose: string
{
    case Login = 'login';
    case Recover = 'recover';
    case EmailVerify = 'email_verify';
}
