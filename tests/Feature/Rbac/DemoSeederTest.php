<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Models\Order;
use App\Domain\Subscriptions\Models\Subscription;
use Database\Seeders\Lookups\DeviceModelSeeder;
use Database\Seeders\Lookups\RegionSeeder;
use Database\Seeders\Lookups\SimOperatorSeeder;
use Database\Seeders\Rbac\DemoDataSeeder;
use Illuminate\Support\Facades\DB;

it('seeds realistic demo data across entities and is idempotent', function () {
    $this->seed(SimOperatorSeeder::class);
    $this->seed(RegionSeeder::class);
    $this->seed(DeviceModelSeeder::class);
    makeSuperAdmin();

    (new DemoDataSeeder)->run();

    expect(Device::query()->where('serial', 'like', 'DEMO-%')->count())->toBeGreaterThan(15)
        ->and(Order::query()->where('reference', 'like', 'DEMO-ORD-%')->count())->toBeGreaterThan(8)
        ->and(Subscription::query()->count())->toBeGreaterThan(10)
        ->and(DB::table('refunds')->count())->toBeGreaterThan(0)
        ->and(DB::table('whitelist_changes')->count())->toBeGreaterThan(15)
        ->and(AuditLog::query()->count())->toBeGreaterThan(3)
        ->and(DB::table('complexes')->where('code', 'like', 'DEMO-CX-%')->count())->toBe(10);

    $devicesAfterFirst = Device::query()->where('serial', 'like', 'DEMO-%')->count();
    (new DemoDataSeeder)->run(); // re-run: must not duplicate
    expect(Device::query()->where('serial', 'like', 'DEMO-%')->count())->toBe($devicesAfterFirst);
});
