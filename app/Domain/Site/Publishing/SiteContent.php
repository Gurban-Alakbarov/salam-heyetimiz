<?php

namespace App\Domain\Site\Publishing;

use App\Domain\Site\Models\SiteDocument;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * The website's content, ready to build: menus and shared texts, the pages that go on the site, the icons.
 * A translated value is an array keyed by language; anything else is the same in every language.
 */
final class SiteContent
{
    /** @var array<string, array<string, mixed>> Pages that go on the website, keyed by page key. */
    public readonly array $pages;

    /**
     * @param  array<string, mixed>  $site
     * @param  array<string, string>  $icons
     * @param  list<array<string, mixed>>  $pages
     */
    public function __construct(public readonly array $site, public readonly array $icons, array $pages, ?Carbon $today = null)
    {
        $today ??= Carbon::today();

        $live = [];
        foreach ($pages as $page) {
            $page['sections'] = array_map(fn (array $s) => self::prepare($s, $today), $page['sections']);
            if (self::hasContent($page)) {
                $live[$page['key']] = $page;
            }
        }
        $this->pages = $live;
    }

    /** The content files in the repository (site/content). */
    public static function fromFiles(string $path, ?Carbon $today = null): self
    {
        return new self(
            self::read($path.'/site.json'),
            self::read($path.'/icons.json'),
            array_map(self::read(...), glob($path.'/pages/*.json') ?: []),
            $today,
        );
    }

    /** The current drafts in the database. */
    public static function fromDatabase(string $iconsFile, ?Carbon $today = null): self
    {
        $documents = SiteDocument::query()->get()->keyBy('key');
        $shared = $documents->pull(SiteDocument::SHARED) ?? throw new RuntimeException('Website content is not imported yet.');

        return new self($shared->content, self::read($iconsFile), $documents->pluck('content')->all(), $today);
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
