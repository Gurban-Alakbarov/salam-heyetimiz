<?php

namespace App\Console\Commands;

use App\Domain\Site\Publishing\SiteBuilder;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** php artisan site:build — renders the website into a folder, ready to copy to the web root. */
class BuildSite extends Command
{
    protected $signature = 'site:build {--out= : Empty target folder (default: storage/app/site-build, replaced on each run)}';

    protected $description = 'Render the website content into static HTML pages';

    public function handle(): int
    {
        $source = base_path('site');
        $out = $this->option('out');
        if (! $out) {
            $out = storage_path('app/site-build');
            File::deleteDirectory($out);
        }

        $files = (new SiteBuilder($source))->build(new SiteContent($source.'/content'), $out);
        $this->info(count($files).' pages written to '.$out);

        return self::SUCCESS;
    }
}
