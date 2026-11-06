<?php

namespace App\Domain\Site\Publishing;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The website's content: site.json (menus, shared texts), pages/*.json (sections in order), icons.json.
 * A translated value is an array keyed by language; anything else is the same in every language.
 */
final class SiteContent
{
    /** @var array<string, mixed> */
    public readonly array $site;

    /** @var array<string, string> */
    public readonly array $icons;

    /** @var array<string, array<string, mixed>> Pages that go on the website, keyed by page key. */
    public readonly array $pages;

    public function __construct(string $path, ?Carbon $today = null)
    {
        $today ??= Carbon::today();
        $this->site = self::read($path.'/site.json');
        $this->icons = self::read($path.'/icons.json');

        $pages = [];
        foreach (glob($path.'/pages/*.json') ?: [] as $file) {
            $page = self::read($file);
            $page['sections'] = array_map(fn (array $s) => self::prepare($s, $today), $page['sections']);
            if (self::hasContent($page)) {
                $pages[$page['key']] = $page;
            }
        }
        $this->pages = $pages;
    }

    /** Campaigns past their last day leave the list. */
    private static function prepare(array $section, Carbon $today): array
    {
        if (! SectionCatalog::has($section['type'])) {
            throw new RuntimeException("Unknown website section type: {$section['type']}");
        }
        if ($section['type'] === 'campaign-list') {
            $section['items'] = array_values(array_filter(
                $section['items'],
                fn (array $c) => empty($c['ends_at']) || ! Carbon::parse($c['ends_at'])->lt($today),
            ));
        }

        return $section;
    }

    /** With no running campaign the campaigns page does not exist. */
    private static function hasContent(array $page): bool
    {
        $lists = array_filter($page['sections'], fn (array $s) => $s['type'] === 'campaign-list');

        return $lists === [] || array_filter($lists, fn (array $s) => $s['items'] !== []) !== [];
    }

    private static function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            throw new RuntimeException("Website content file is not valid: {$file}");
        }

        return $data;
    }
}
