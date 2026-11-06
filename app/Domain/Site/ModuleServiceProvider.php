<?php

namespace App\Domain\Site;

use App\Domain\Site\Publishing\SiteLivePointer;
use App\Domain\Site\Publishing\SitePublisher;
use Illuminate\Support\ServiceProvider;

/** The public website: its content, requests from it and publishing. */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SiteLivePointer::class, fn () => new SiteLivePointer((string) config('site.releases_path')));

        $this->app->bind(SitePublisher::class, fn ($app) => new SitePublisher(
            $app->make(SiteLivePointer::class),
            (string) config('site.releases_path'),
            base_path('site'),
        ));
    }
}
