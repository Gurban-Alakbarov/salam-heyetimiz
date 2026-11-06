<?php

namespace App\Domain\Site\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Why the website could not be published or rolled back. */
final class SitePublishException extends DomainException
{
    /** @param array<string, mixed>|null $info */
    private function __construct(private readonly string $code_, private readonly int $status, private readonly ?array $info = null)
    {
        parent::__construct($code_);
    }

    public static function busy(): self
    {
        return new self('site_publish_busy', 409);
    }

    public static function nothingToPublish(): self
    {
        return new self('site_nothing_to_publish', 409);
    }

    /** @param list<string> $pages */
    public static function unfinished(array $pages): self
    {
        return new self('site_publish_unfinished', 422, ['pages' => $pages]);
    }

    public static function releaseGone(): self
    {
        return new self('site_release_gone', 409);
    }

    public function errorCode(): string
    {
        return $this->code_;
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function messageKey(): string
    {
        return 'errors.'.$this->code_;
    }

    public function details(): ?array
    {
        return $this->info;
    }
}
