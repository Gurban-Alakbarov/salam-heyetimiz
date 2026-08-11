<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovers every domain module's ModuleServiceProvider so a new module is
 * added without touching bootstrap/providers.php or config (Backend Arch §3.1).
 *
 * Convention: app/Domain/<Module>/ModuleServiceProvider.php → class
 * App\Domain\<Module>\ModuleServiceProvider.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->moduleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function moduleProviders(): array
    {
        $domainPath = app_path('Domain');

        if (! is_dir($domainPath)) {
            return [];
        }

        $providers = [];

        foreach (glob($domainPath.'/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $module = basename($moduleDir);
            $class = "App\\Domain\\{$module}\\ModuleServiceProvider";

            if (is_file($moduleDir.'/ModuleServiceProvider.php') && class_exists($class)) {
                $providers[] = $class;
            }
        }

        sort($providers); // deterministic registration order

        return $providers;
    }
}
