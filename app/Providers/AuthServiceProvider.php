<?php

namespace App\Providers;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Model → Policy map. Policies are added by their owning module increments
     * (Backend Arch §7.2); empty in the foundations commit.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Coarse role gates (Backend Arch §7.3). The two disjoint JWT guards
        // (mobile / admin, R-API-03) and the tfa_verified claim gate are wired in
        // the Auth-module increment.
        Gate::define('admin.role.super', static fn ($actor): bool => $actor instanceof AdminUser
            && $actor->role === AdminRole::SuperAdmin);

        Gate::define('admin.role.technical', static fn ($actor): bool => $actor instanceof AdminUser
            && in_array($actor->role, [AdminRole::Technical, AdminRole::SuperAdmin], true));

        Gate::define('maintenance.bypass', static fn ($actor): bool => $actor instanceof AdminUser
            && $actor->role === AdminRole::SuperAdmin);
    }
}
