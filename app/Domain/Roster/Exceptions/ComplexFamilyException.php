<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** O7 — refusals of the Komendant's family actions. Nothing is changed when one is thrown. */
final class ComplexFamilyException extends DomainException
{
    /** @param  array<string, mixed>|null  $detail */
    private function __construct(private readonly string $reason, private readonly int $status, private readonly ?array $detail = null)
    {
        parent::__construct($reason);
    }

    /** The resident heads a family in the complex: say whether a member takes over or the family leaves too. */
    public static function decisionRequired(array $members): self
    {
        return new self('family_decision_required', 409, ['members' => $members]);
    }

    /** The user does not head a family on this complex's devices. */
    public static function notHead(): self
    {
        return new self('not_family_head', 422);
    }

    /** The chosen new head is not a member of that family on this complex's devices. */
    public static function notMember(): self
    {
        return new self('not_family_member', 422);
    }

    public static function unknownAction(): self
    {
        return new self('family_action_invalid', 422);
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
        return "errors.{$this->reason}";
    }

    public function details(): ?array
    {
        return $this->detail;
    }
}
