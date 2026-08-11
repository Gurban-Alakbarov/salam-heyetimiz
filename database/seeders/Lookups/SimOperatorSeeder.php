<?php

namespace Database\Seeders\Lookups;

use App\Domain\Catalog\Models\SimOperator;
use Illuminate\Database\Seeder;

/**
 * The three Azerbaijani operators in scope (DB Arch §11.1). The per-operator
 * CLIP default driver is decided in Phase 0 (CRIT-01) and lives in
 * config/domain/device_comm.php, not here.
 */
class SimOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            ['code' => 'azercell', 'name' => 'Azercell', 'mcc_mnc' => '400-01'],
            ['code' => 'bakcell', 'name' => 'Bakcell', 'mcc_mnc' => '400-02'],
            ['code' => 'nar', 'name' => 'Nar', 'mcc_mnc' => '400-04'],
        ];

        foreach ($operators as $operator) {
            SimOperator::query()->updateOrCreate(
                ['code' => $operator['code']],
                $operator + ['country_iso' => 'AZ', 'is_active' => true],
            );
        }
    }
}
