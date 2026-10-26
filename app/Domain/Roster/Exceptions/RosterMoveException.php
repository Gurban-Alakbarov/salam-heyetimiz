<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Y5 — refusals of an admin "move resident to another gate". Nothing is changed when one is thrown. */
final class RosterMoveException extends DomainException
{
    private function __construct(private readonly string $reason, private readonly int $status)
    {
        parent::__construct($reason);
    }

    public static function sameDevice(): self
    {
        return new self('move_same_device', 422);
    }

    /** Only a plain resident row moves: an owner owns the device, a family member's access follows its head. */
    public static function notMovable(): self
    {
        return new self('move_not_movable', 422);
    }

    public static function targetInactive(): self
    {
        return new self('move_target_inactive', 422);
    }

    /** A complex gate is opened by members of that complex only. */
    public static function notComplexMember(): self
    {
        return new self('move_not_complex_member', 422);
    }

    /** The resident already pays for the target gate — moving would merge two paid periods. */
    public static function targetAlreadySubscribed(): self
    {
        return new self('move_target_subscribed', 409);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function messageKey(): string
    {
        return 'errors.'.$this->reason;
    }
}
