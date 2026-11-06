<?php

namespace App\Console\Commands;

use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Publishing\SitePublisher;
use Illuminate\Console\Command;

/** php artisan site:rebuild — builds the published content again; needed after a deploy and when a campaign ends. */
class RebuildSite extends Command
{
    protected $signature = 'site:rebuild';

    protected $description = 'Rebuild the live website from what is already published';

    public function handle(SitePublisher $publisher): int
    {
        if (! SiteDocument::query()->exists()) {
            $this->info('No website content in the database yet.');

            return self::SUCCESS;
        }

        $release = $publisher->rebuild();
        $this->info($release === null ? 'The website is already up to date.' : "Website rebuilt: {$release->directory}");

        return self::SUCCESS;
    }
}
