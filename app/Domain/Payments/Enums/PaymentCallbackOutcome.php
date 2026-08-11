<?php

namespace App\Domain\Payments\Enums;

enum PaymentCallbackOutcome: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case SignatureInvalid = 'signature_invalid';
    case Unmatched = 'unmatched';
    case Error = 'error';
}
