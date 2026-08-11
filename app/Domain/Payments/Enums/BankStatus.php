<?php

namespace App\Domain\Payments\Enums;

/**
 * Authoritative status returned by Kapital Bank (callback body + getOrderStatus).
 * Per R-PAY-04 the bank's getOrderStatus value is authoritative, never the callback body.
 */
enum BankStatus: string
{
    case Approved = 'APPROVED';
    case Declined = 'DECLINED';
    case Canceled = 'CANCELED';
    case Refunded = 'REFUNDED';
    case Pending = 'PENDING';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtoupper(trim($value)));
    }

    /** Maps a terminal bank status to the order status it drives. PENDING returns null (no transition). */
    public function toOrderStatus(): ?OrderStatus
    {
        return match ($this) {
            self::Approved => OrderStatus::Paid,
            self::Declined => OrderStatus::Failed,
            self::Canceled => OrderStatus::Cancelled,
            self::Refunded => OrderStatus::Refunded,
            self::Pending => null,
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
