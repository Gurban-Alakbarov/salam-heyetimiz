<?php

namespace Database\Seeders\Lookups;

use App\Domain\Catalog\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Baku and its districts (DB Arch §11.1). Districts reference Baku via parent_id.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $baku = Region::query()->updateOrCreate(
            ['code' => 'baku'],
            ['name' => 'Bakı', 'parent_id' => null, 'is_active' => true],
        );

        $districts = [
            'baku-yasamal' => 'Yasamal',
            'baku-nasimi' => 'Nəsimi',
            'baku-nizami' => 'Nizami',
            'baku-binagadi' => 'Binəqədi',
            'baku-sabail' => 'Səbail',
            'baku-narimanov' => 'Nərimanov',
            'baku-xatai' => 'Xətai',
            'baku-surakhani' => 'Suraxanı',
        ];

        foreach ($districts as $code => $name) {
            Region::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'parent_id' => $baku->id, 'is_active' => true],
            );
        }
    }
}
