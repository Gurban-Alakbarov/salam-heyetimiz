<?php

namespace App\Domain\Payments\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Order is not in a refundable state, or the amount exceeds the net captured → 409. */
class OrderNotRefundableException extends DomainException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(string $message = 'Order is not refundable.', private readonly ?array $detailData = null)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'order_not_refundable';
    }

    public function messageKey(): string
    {
        return 'errors.order_not_refundable';
    }

    public function details(): ?array
    {
        return $this->detailData;
    }
}
