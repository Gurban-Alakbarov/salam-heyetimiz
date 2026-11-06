<?php

namespace App\Console\Commands;

use App\Domain\Site\Content\SiteDocumentService;
use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Console\Command;

/** php artisan site:import — fills the website content tables from site/content, once. */
class ImportSiteContent extends Command
{
    protected $signature = 'site:import';

    protected $description = 'Import the website content files into the database (only when it is still empty)';

    public function handle(SiteDocumentService $documents): int
    {
        if (SiteDocument::query()->exists()) {
            $this->info('Website content is already in the database, nothing to do.');

            return self::SUCCESS;
        }

        $path = base_path('site/content');
        $pages = array_map(
            fn (string $file) => json_decode((string) file_get_contents($file), true),
            glob($path.'/pages/*.json') ?: [],
        );

        $count = $documents->import(SiteContent::fromFiles($path), $pages);
        $this->info("{$count} website documents imported.");

        return self::SUCCESS;
    }
}
