<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Realistic demo data so every role lands on meaningful, populated screens (idempotent). Complexes →
 * devices (across complexes, online/offline mix) → resident owners + members → subscriptions → orders +
 * refunds → whitelist entries → audit log. The complex_manager demo (scoped to "Salam Həyət A") gets extra
 * devices in that complex. Apartments/buildings/entrances/vehicles are not seeded — those entities have no
 * schema yet (RBAC permissions exist; tables ship with the feature).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $superId = (int) AdminUser::query()->where('role', 'super_admin')->orderBy('id')->value('id');

        // Resolve FK targets dynamically (ids are stable in prod but drift under test auto-increment).
        $modelId = (int) DB::table('device_models')->min('id');
        $operatorIds = DB::table('sim_operators')->orderBy('id')->pluck('id')->all() ?: [null];
        $regionIds = DB::table('regions')->orderBy('id')->pluck('id')->all() ?: [null];

        $complexes = [
            'Sea Breeze', 'Ağ Şəhər', 'Port Baku Residence', 'Crescent Bay', 'Knightsbridge Residence',
            'Park Azure', 'White City Towers', 'Grand Hayat', 'Olympic Residence', 'Yasamal Residence',
        ];
        $complexIds = [];
        foreach ($complexes as $i => $name) {
            $complexIds[] = (int) Complex::query()->updateOrCreate(
                ['code' => 'DEMO-CX-'.($i + 1)],
                ['name' => $name, 'region_id' => $regionIds[$i % count($regionIds)], 'is_active' => true],
            )->id;
        }
        // Manager demo is scoped to the seeded "Salam Həyət A" (code SALAM-A) — give it extra devices.
        $managerComplexId = (int) (Complex::query()->where('code', 'SALAM-A')->value('id') ?? $complexIds[0]);

        // Devices: 2 per demo complex + 3 extra in the manager's complex. Half online, half offline.
        $deviceSpecs = [];
        foreach ($complexIds as $ci => $cid) {
            for ($n = 1; $n <= 2; $n++) {
                $deviceSpecs[] = [$cid, $ci, $n];
            }
        }
        for ($n = 1; $n <= 3; $n++) {
            $deviceSpecs[] = [$managerComplexId, 99, $n];
        }

        $seq = 0;
        foreach ($deviceSpecs as [$cid, $ci, $n]) {
            $seq++;
            $online = $seq % 2 === 0;
            $serial = 'DEMO-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $ownerPhone = sprintf('+99455%07d', 1000000 + $seq);

            /** @var User $owner */
            $owner = User::query()->updateOrCreate(
                ['phone' => $ownerPhone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => 'active'],
            );

            /** @var Device $device */
            $device = Device::query()->updateOrCreate(
                ['serial' => $serial],
                [
                    'device_model_id' => $modelId,
                    'sim_phone' => sprintf('+99450%07d', 2000000 + $seq),
                    'sim_operator_id' => $operatorIds[$seq % count($operatorIds)],
                    'driver_type' => 'traccar',
                    'status' => 'active',
                    'owner_user_id' => $owner->id,
                    'region_id' => $regionIds[$ci % count($regionIds)],
                    'complex_id' => $cid,
                    'location_label' => 'Giriş '.(($seq % 4) + 1),
                    // Mixed on purpose: some rows stay NULL so the app's image/address
                    // fallbacks (premium placeholder / "Ünvan daxil edilməyib") are exercised.
                    'image_url' => $seq % 3 === 0 ? null : 'https://picsum.photos/seed/salam'.$seq.'/640/360',
                    'address' => $seq % 4 === 0 ? null : 'Nizami küç. '.($seq * 3).', Bakı',
                    'last_online_at' => $online ? now()->subMinutes(2) : now()->subHours(6),
                    'last_signal_strength' => $online ? 28 : 12,
                ],
            );

            // owner roster row + whitelist
            $ownerDu = $this->roster($device->id, $owner->id, 'owner');
            $this->whitelist($device->id, $ownerDu->id, $ownerPhone, $seq % 3 === 0 ? 'pending' : 'synced');
            // active subscription for most owners; a few expired
            $this->subscription($ownerDu->id, $seq % 5 === 0 ? 'expired' : 'active');

            // every 2nd device gets an extra resident (member)
            if ($seq % 2 === 0) {
                $memberPhone = sprintf('+99477%07d', 3000000 + $seq);
                $member = User::query()->updateOrCreate(
                    ['phone' => $memberPhone],
                    ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => 'active'],
                );
                $memberDu = $this->roster($device->id, $member->id, 'user');
                $this->whitelist($device->id, $memberDu->id, $memberPhone, 'synced');
            }
        }

        // Orders + refunds (finance/support screens)
        $payers = User::query()->where('phone', 'like', '+99455%')->limit(12)->pluck('id')->all();
        $statuses = ['paid', 'paid', 'paid', 'pending', 'authorising', 'refunded', 'partially_refunded', 'failed'];
        foreach ($payers as $idx => $payerId) {
            $status = $statuses[$idx % count($statuses)];
            $amount = 1200 + ($idx % 4) * 600;
            /** @var Order $order */
            $order = Order::query()->updateOrCreate(
                ['reference' => 'DEMO-ORD-'.($idx + 1)],
                [
                    'payer_user_id' => $payerId,
                    'purpose' => $idx % 2 === 0 ? 'sub_main' : 'device_sale',
                    'amount_minor' => $amount,
                    'currency' => 'AZN',
                    'status' => $status,
                    'bank_order_id' => 'KB-DEMO-'.($idx + 1),
                    'paid_at' => in_array($status, ['paid', 'refunded', 'partially_refunded'], true) ? now()->subDays($idx + 1) : null,
                ],
            );
            if (in_array($status, ['paid', 'refunded', 'partially_refunded'], true)) {
                $order->payments()->updateOrCreate(
                    ['bank_transaction_id' => 'TXN-DEMO-'.($idx + 1)],
                    ['type' => 'charge', 'amount_minor' => $amount, 'currency' => 'AZN', 'status' => 'approved', 'occurred_at' => now()->subDays($idx + 1)],
                );
            }
            if (in_array($status, ['refunded', 'partially_refunded'], true) && $superId > 0) {
                DB::table('refunds')->updateOrInsert(
                    ['order_id' => $order->id, 'reason' => 'Demo refund'],
                    [
                        'amount_minor' => $status === 'refunded' ? $amount : (int) ($amount / 2),
                        'status' => 'approved',
                        'requested_by_admin_id' => $superId,
                        'processed_by_admin_id' => $superId,
                        'created_at' => now()->subDays($idx),
                        'updated_at' => now()->subDays($idx),
                    ],
                );
            }
        }

        // A few audit-log entries so the Audit page is populated
        if ($superId > 0) {
            foreach ([
                ['admin.login', null, null],
                ['device.assigned', Device::class, 1],
                ['roster.user_added', Device::class, 1],
                ['settings.updated', null, null],
                ['admin.impersonation.start', AdminUser::class, $superId],
            ] as $k => [$action, $type, $tid]) {
                AuditLog::query()->firstOrCreate(
                    ['action' => $action, 'actor_id' => $superId, 'auditable_id' => $tid, 'auditable_type' => $type],
                    [
                        'actor_kind' => 'admin',
                        'actor_label' => 'super@salamheyetimiz.com',
                        'payload' => ['demo' => true],
                        'ip' => '185.208.206.174',
                        'created_at' => now()->subHours($k + 1),
                    ],
                );
            }
        }
    }

    private function roster(int $deviceId, int $userId, string $role): DeviceUser
    {
        return DeviceUser::query()->updateOrCreate(
            ['device_id' => $deviceId, 'user_id' => $userId],
            ['role' => $role, 'status' => 'active'],
        );
    }

    private function whitelist(int $deviceId, int $deviceUserId, string $phone, string $status): void
    {
        /** @var WhitelistChange $row */
        $row = WhitelistChange::query()->updateOrCreate(
            ['device_id' => $deviceId, 'device_user_id' => $deviceUserId, 'action' => 'add', 'phone' => $phone],
            ['priority' => 50, 'status' => $status, 'attempt_count' => $status === 'synced' ? 1 : 0],
        );
        if ($row->seq === null) {
            $row->forceFill(['seq' => $row->id])->save();
        }
    }

    private function subscription(int $deviceUserId, string $status): void
    {
        Subscription::query()->updateOrCreate(
            ['device_user_id' => $deviceUserId],
            [
                'tier' => 'main',
                'price_minor' => 1200,
                'currency' => 'AZN',
                'term_days' => 365,
                'starts_at' => $status === 'expired' ? now()->subDays(400) : now()->subDays(30),
                'ends_at' => $status === 'expired' ? now()->subDays(35) : now()->addDays(335),
                'status' => $status,
                'auto_renew' => false,
            ],
        );
    }
}
