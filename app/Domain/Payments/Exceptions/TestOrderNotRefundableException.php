<?php

namespace App\Domain\Payments\Exceptions;

/** The order was paid on the simulated checkout: no money was taken, and the bank has never heard of it → 409. */
class TestOrderNotRefundableException extends OrderNotRefundableException
{
    public function errorCode(): string
    {
        return 'test_order_not_refundable';
    }

    public function messageKey(): string
    {
        return 'errors.test_order_not_refundable';
    }
}
