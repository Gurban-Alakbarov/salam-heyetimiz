<?php

namespace App\Domain\Site\Publishing;

/**
 * The link the web server follows: `current` inside the releases folder. Replacing it with a rename is one atomic step,
 * so a visitor gets either the old site or the new one, never a mix, and going back is the same step in reverse.
 */
class SiteLivePointer
{
    public function __construct(private readonly string $root) {}

    /** Name of the release folder that is live, if any. */
    public function current(): ?string
    {
        $link = $this->root.'/current';

        return is_link($link) ? basename((string) readlink($link)) : null;
    }

    public function point(string $directory): void
    {
        $link = $this->root.'/current';
        $next = $link.'.next';

        @unlink($next);
        symlink($directory, $next); // relative, so the releases folder can move
        rename($next, $link);
    }
}
