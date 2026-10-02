<?php

namespace App\Domain\Applications\Enums;

/** Physical-person application pipeline (IMPLEMENTATION_PLAN §10). installed / rejected are terminal. */
enum IndividualApplicationStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case InProgress = 'in_progress';
    case Installed = 'installed';
    case Rejected = 'rejected';

    /** @return array<int, self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Contacted, self::InProgress, self::Rejected],
            self::Contacted => [self::InProgress, self::Rejected],
            self::InProgress => [self::Installed, self::Rejected],
            self::Installed, self::Rejected => [],
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Contacted, self::InProgress], true);
    }
}
