<?php

namespace App\Domain\Site\Content;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Site\Exceptions\SiteContentException;
use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Support\Facades\DB;

/** Saves website content. Every change becomes a revision, so any earlier state can be seen and brought back. */
final class SiteDocumentService
{
    /** Revisions kept per document, besides the published one. */
    private const KEEP = 50;

    public static function iconsFile(): string
    {
        return base_path('site/content/icons.json');
    }

    /** @return list<string> */
    public static function iconNames(): array
    {
        return array_keys((array) json_decode((string) file_get_contents(self::iconsFile()), true));
    }

    /** First fill from the repository's files. The website already shows this content, so it counts as published. */
    public function import(SiteContent $files, array $pageFiles): int
    {
        $normalizer = new ContentNormalizer(array_keys($files->icons));

        return DB::transaction(function () use ($normalizer, $files, $pageFiles): int {
            $this->create(SiteDocument::SHARED, $normalizer->shared($files->site, $files->site));
            foreach ($pageFiles as $page) {
                $this->create($page['key'], $normalizer->page($page, $page['key']));
            }

            return SiteDocument::query()->count();
        });
    }

    /** @throws SiteContentException when someone else saved since $expectedVersion was read */
    public function save(string $key, array $input, int $expectedVersion, AdminUser $admin): SiteDocument
    {
        return DB::transaction(function () use ($key, $input, $expectedVersion, $admin): SiteDocument {
            $document = $this->lock($key, $expectedVersion);
            $normalizer = new ContentNormalizer(self::iconNames());
            $content = $key === SiteDocument::SHARED
                ? $normalizer->shared($input, $document->content)
                : $normalizer->page($input, $key, $document->content);

            return $this->store($document, $content, $admin, null);
        });
    }

    public function restore(string $key, int $version, int $expectedVersion, AdminUser $admin): SiteDocument
    {
        return DB::transaction(function () use ($key, $version, $expectedVersion, $admin): SiteDocument {
            $document = $this->lock($key, $expectedVersion);
            $revision = $document->revisions()->where('version', $version)->firstOrFail();

            return $this->store($document, $revision->content, $admin, $version);
        });
    }

    private function lock(string $key, int $expectedVersion): SiteDocument
    {
        $document = SiteDocument::query()->where('key', $key)->lockForUpdate()->firstOrFail();
        if ($document->version !== $expectedVersion) {
            throw new SiteContentException;
        }

        return $document;
    }

    private function create(string $key, array $content): void
    {
        $document = SiteDocument::query()->create(['key' => $key, 'content' => $content, 'version' => 1, 'published_version' => 1]);
        $document->revisions()->create(['version' => 1, 'content' => $content]);
    }

    private function store(SiteDocument $document, array $content, AdminUser $admin, ?int $restoredFrom): SiteDocument
    {
        if (self::canonical($content) === self::canonical($document->content)) {
            return $document;
        }

        $document->forceFill([
            'content' => $content,
            'version' => $document->version + 1,
            'updated_by_admin_id' => (int) $admin->getKey(),
        ])->save();

        $document->revisions()->create([
            'version' => $document->version,
            'content' => $content,
            'admin_id' => (int) $admin->getKey(),
            'restored_from' => $restoredFrom,
        ]);

        $document->revisions()
            ->where('version', '<=', $document->version - self::KEEP)
            ->when($document->published_version !== null, fn ($q) => $q->where('version', '!=', $document->published_version))
            ->delete();

        return $document;
    }

    /** The database may return object keys in another order, which is not a change. */
    private static function canonical(array $value): string
    {
        $sort = function (array $v) use (&$sort): array {
            ksort($v);

            return array_map(fn ($x) => is_array($x) ? $sort($x) : $x, $v);
        };

        return (string) json_encode($sort($value));
    }
}
