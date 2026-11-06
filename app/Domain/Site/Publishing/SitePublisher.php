<?php

namespace App\Domain\Site\Publishing;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Site\Content\SiteDocumentService;
use App\Domain\Site\Content\TranslationState;
use App\Domain\Site\Exceptions\SitePublishException;
use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Models\SiteRelease;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Puts the website live. Each publish builds a complete copy in its own folder and only then switches to it, so a failed
 * build changes nothing and an earlier copy is one step away.
 */
class SitePublisher
{
    /** Copies kept on disk, besides the live one. */
    private const KEEP = 10;

    private const LOCK_SECONDS = 180;

    public function __construct(private readonly SiteLivePointer $pointer, private readonly string $root, private readonly string $source) {}

    /** What publishing would do now, and what can be gone back to. */
    public function status(): array
    {
        $documents = SiteDocument::query()->orderBy('id')->get();
        $changed = $documents->filter(fn (SiteDocument $d) => $d->hasUnpublishedChanges());

        $issues = $changed->flatMap(fn (SiteDocument $d) => (new TranslationState)->issues($d->content));
        $live = $this->pointer->current();

        return [
            'changed' => $changed->map(fn (SiteDocument $d) => [
                'key' => $d->key,
                'title' => self::title($d),
                'version' => $d->version,
                'published_version' => $d->published_version,
            ])->values()->all(),
            'translations_missing' => $issues->where('state', 'missing')->count(),
            'translations_stale' => $issues->where('state', 'stale')->count(),
            'unfinished' => $this->unfinished($documents),
            'releases' => SiteRelease::query()->with('admin')->orderByDesc('id')->get()->map(fn (SiteRelease $r) => [
                'id' => (int) $r->id,
                'kind' => $r->kind,
                'admin' => $r->admin?->name,
                'pages' => $r->pages,
                'live' => $r->directory === $live,
                'created_at' => optional($r->created_at)->toIso8601String(),
            ])->all(),
        ];
    }

    /** @throws SitePublishException */
    public function publish(AdminUser $admin): SiteRelease
    {
        return $this->locked(function () use ($admin): SiteRelease {
            $documents = SiteDocument::query()->get();
            if ($documents->every(fn (SiteDocument $d) => ! $d->hasUnpublishedChanges())) {
                throw SitePublishException::nothingToPublish();
            }
            $unfinished = $this->unfinished($documents);
            if ($unfinished !== []) {
                throw SitePublishException::unfinished($unfinished);
            }

            $content = SiteContent::fromDocuments($documents, SiteDocumentService::iconsFile());
            $versions = $documents->pluck('version', 'key')->all();
            $release = $this->buildRelease($content, $versions, 'publish', (int) $admin->getKey());

            $this->pointer->point($release->directory);
            $this->markPublished($versions);
            $this->prune();

            return $release;
        });
    }

    /**
     * Builds what is already published again, for what changes without an edit: a campaign's last day passing, or new
     * templates after a deploy. A result identical to the live site is thrown away.
     */
    public function rebuild(): ?SiteRelease
    {
        return $this->locked(function (): ?SiteRelease {
            $documents = SiteDocument::query()->get();
            $content = SiteContent::fromPublished($documents, SiteDocumentService::iconsFile());
            $release = $this->buildRelease($content, $documents->pluck('published_version', 'key')->all(), 'rebuild', null);

            $live = $this->pointer->current();
            if ($live !== null && self::fingerprint($this->path($live)) === self::fingerprint($this->path($release->directory))) {
                File::deleteDirectory($this->path($release->directory));
                $release->delete();

                return null;
            }

            $this->pointer->point($release->directory);
            $this->prune();

            return $release;
        });
    }

    /** Goes back to an earlier copy. Drafts stay as they are and show up as not published. */
    public function rollback(SiteRelease $release): void
    {
        $this->locked(function () use ($release): void {
            if (! is_dir($this->path($release->directory))) {
                throw SitePublishException::releaseGone();
            }

            $this->pointer->point($release->directory);
            $this->markPublished($release->versions);
        });
    }

    /** The saved draft of a page as a visitor would see it, ready to show inside the panel. Null when it has no page. */
    public function preview(string $key, string $lang): ?string
    {
        $content = SiteContent::fromDatabase(SiteDocumentService::iconsFile());
        $page = $content->pages[$key === SiteDocument::SHARED ? 'index' : $key] ?? null;
        if ($page === null) {
            return null;
        }

        $base = rtrim((string) $content->site['base_url'], '/');
        $html = (new SiteBuilder($this->source))->renderPage($content, $page, $lang);
        // Paths resolve against the public site; images not published yet are read from where uploads wait.
        $html = str_replace('="/landing/media/', '="/storage/site-media/', $html);

        return str_replace('<head>', '<head><base href="'.e($base).'/"/><style>a,button,summary{pointer-events:none}</style>', $html);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function locked(callable $work): mixed
    {
        $lock = Cache::lock('site-publish', self::LOCK_SECONDS);
        if (! $lock->get()) {
            throw SitePublishException::busy();
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, int|null> $versions */
    private function buildRelease(SiteContent $content, array $versions, string $kind, ?int $adminId): SiteRelease
    {
        File::ensureDirectoryExists($this->root);
        $directory = now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));

        try {
            $files = (new SiteBuilder($this->source))->build($content, $this->path($directory));
        } catch (Throwable $e) {
            File::deleteDirectory($this->path($directory));

            throw $e;
        }

        return SiteRelease::query()->create([
            'directory' => $directory,
            'kind' => $kind,
            'versions' => $versions,
            'pages' => count($files),
            'admin_id' => $adminId,
        ]);
    }

    /** @param array<string, int|null> $versions */
    private function markPublished(array $versions): void
    {
        DB::transaction(function () use ($versions): void {
            foreach ($versions as $key => $version) {
                SiteDocument::query()->where('key', $key)->update(['published_version' => $version]);
            }
        });
    }

    private function prune(): void
    {
        $live = $this->pointer->current();
        SiteRelease::query()->orderByDesc('id')->skip(self::KEEP)->take(PHP_INT_MAX)->get()
            ->reject(fn (SiteRelease $r) => $r->directory === $live)
            ->each(function (SiteRelease $r): void {
                File::deleteDirectory($this->path($r->directory));
                $r->delete();
            });
    }

    /** Pages that would go out broken. Translations only warn: Azerbaijani stands in for a missing one. */
    private function unfinished(Collection $documents): array
    {
        return $documents
            ->reject(fn (SiteDocument $d) => $d->key === SiteDocument::SHARED)
            ->filter(fn (SiteDocument $d) => trim((string) ($d->content['title']['az'] ?? '')) === '')
            ->map(fn (SiteDocument $d) => $d->key)
            ->values()
            ->all();
    }

    private static function title(SiteDocument $document): string
    {
        return $document->key === SiteDocument::SHARED ? 'Ümumi mətnlər' : (string) ($document->content['title']['az'] ?? $document->key);
    }

    private function path(string $directory): string
    {
        return $this->root.'/'.$directory;
    }

    private static function fingerprint(string $directory): string
    {
        $parts = [];
        foreach (File::allFiles($directory) as $file) {
            $parts[$file->getRelativePathname()] = md5_file($file->getPathname());
        }
        ksort($parts);

        return md5((string) json_encode($parts));
    }
}
