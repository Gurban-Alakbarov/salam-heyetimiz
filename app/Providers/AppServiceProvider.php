<?php

namespace App\Providers;

use App\Support\Time\Clock;
use App\Support\Time\SystemClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Injectable clock for testability (R-CODE-08).
        $this->app->bind(Clock::class, SystemClock::class);

        // Laravel only auto-loads top-level config/*.php; the documented config/domain/* and
        // config/integrations/* sub-directories (BACKEND §3) must be merged explicitly under their
        // namespaced keys. Forward-slash normalisation handles Windows glob (same as migrations).
        foreach (['domain', 'integrations'] as $namespace) {
            $dir = str_replace('\\', '/', config_path($namespace));

            foreach (glob($dir.'/*.php') ?: [] as $file) {
                $this->mergeConfigFrom($file, $namespace.'.'.basename($file, '.php'));
            }
        }
    }

    public function boot(): void
    {
        // Migrations are organised in batch subfolders (DB Arch §11). The default
        // database/migrations glob is not recursive, so register each subfolder.
        // Normalise to forward slashes: on Windows, glob() does not match a pattern
        // that mixes backslash path separators with a trailing "/*" (returns 0),
        // whereas an all-forward-slash pattern works on both Windows and Linux.
        $base = str_replace('\\', '/', database_path('migrations'));
        $paths = glob($base.'/*', GLOB_ONLYDIR);

        if (is_array($paths) && $paths !== []) {
            $this->loadMigrationsFrom($paths);
        }

        // Catch lazy-loading, mass-assignment and missing-attribute mistakes outside prod.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Money is integer minor units; floats are never used for money (R-DOM-15).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // OpenAPI returns bare objects for single resources and an explicit {data, page}
        // envelope for lists (built per controller). Disable Laravel's implicit "data" wrap so
        // single-resource responses match the contract exactly (R-API-01).
        JsonResource::withoutWrapping();
    }
}
