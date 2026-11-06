<?php

namespace App\Console\Commands;

use App\Domain\Site\Content\SiteDocumentService;
use App\Domain\Site\Publishing\SiteBuilder;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** php artisan site:build — renders the website into a folder, ready to copy to the web root. */
class BuildSite extends Command
{
    protected $signature = 'site:build
        {--out= : Empty target folder (default: storage/app/site-build, replaced on each run)}
        {--from-db : Use the drafts in the database instead of the files in site/content}';

    protected $description = 'Render the website content into static HTML pages';

    public function handle(): int
    {
        $source = base_path('site');
        $out = $this->option('out');
        if (! $out) {
            $out = storage_path('app/site-build');
            File::deleteDirectory($out);
        }

        $content = $this->option('from-db')
            ? SiteContent::fromDatabase(SiteDocumentService::iconsFile())
            : SiteContent::fromFiles($source.'/content');

        $files = (new SiteBuilder($source))->build($content, $out);
        $this->info(count($files).' pages written to '.$out);

        return self::SUCCESS;
    }
}
