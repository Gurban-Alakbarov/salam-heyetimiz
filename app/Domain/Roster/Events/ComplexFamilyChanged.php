<?php

namespace App\Domain\Roster\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * O7 — the Komendant changed a family in its complex: removed a member (`member_removed`) or handed the family
 * to another member (`head_changed`). Fired after the change is committed; the Notifications module tells the
 * people concerned.
 */
final class ComplexFamilyChanged
{
    use Dispatchable;

    public const MEMBER_REMOVED = 'member_removed';

    public const HEAD_CHANGED = 'head_changed';

    /** @param  list<int>  $memberUserIds  the family's (other) members at the time */
    public function __construct(
        public readonly string $kind,
        public readonly int $complexId,
        public readonly int $subjectUserId,
        public readonly int $headUserId,
        public readonly array $memberUserIds = [],
        public readonly ?int $previousHeadUserId = null,
        public readonly bool $previousHeadRemoved = false,
    ) {}
}
