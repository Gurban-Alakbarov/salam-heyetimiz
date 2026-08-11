<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Services\SettingsService;
use Illuminate\Database\Seeder;

/** Seeds the catalog defaults into the settings table (idempotent — never overwrites an existing value). */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(SettingsService::class)->seedDefaults();
    }
}
