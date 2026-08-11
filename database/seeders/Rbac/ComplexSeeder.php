<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Models\Complex;
use Illuminate\Database\Seeder;

/** Demo residential complexes used to scope complex_manager admins (idempotent). */
class ComplexSeeder extends Seeder
{
    public function run(): void
    {
        $complexes = [
            ['code' => 'SALAM-A', 'name' => 'Salam Həyət A', 'region_id' => 1],
            ['code' => 'SALAM-B', 'name' => 'Salam Həyət B', 'region_id' => 2],
        ];

        foreach ($complexes as $complex) {
            Complex::query()->updateOrCreate(
                ['code' => $complex['code']],
                ['name' => $complex['name'], 'region_id' => $complex['region_id'], 'is_active' => true],
            );
        }
    }
}
