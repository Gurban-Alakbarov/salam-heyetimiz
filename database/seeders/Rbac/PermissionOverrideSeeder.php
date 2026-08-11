<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\UserPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts that prove per-user overrides beat the role template (idempotent). Same role, different
 * effective permissions:
 *   - technical1: Technical + GRANT orders.view, refunds.view  → can open Finance pages
 *   - technical2: plain Technical                              → cannot
 *   - finance1:   Finance + REVOKE refunds.create             → no refund button (still Finance)
 *   - operator1:  Operator + GRANT devices.diagnostics.view   → can open Diagnostics
 *   - support1:   Support + GRANT devices.diagnostics.view    → can open Diagnostics
 */
class PermissionOverrideSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => 'technical1@salamheyetimiz.com', 'name' => 'Technical #1', 'role' => 'technical', 'password' => 'Tech1!Salam-RBAC#2026', 'grant' => ['orders.view', 'refunds.view'], 'revoke' => []],
            ['email' => 'technical2@salamheyetimiz.com', 'name' => 'Technical #2', 'role' => 'technical', 'password' => 'Tech2!Salam-RBAC#2026', 'grant' => [], 'revoke' => []],
            ['email' => 'finance1@salamheyetimiz.com', 'name' => 'Finance #1', 'role' => 'finance', 'password' => 'Fin1!Salam-RBAC#2026', 'grant' => [], 'revoke' => ['refunds.create']],
            ['email' => 'operator1@salamheyetimiz.com', 'name' => 'Operator #1', 'role' => 'operator', 'password' => 'Oper1!Salam-RBAC#2026', 'grant' => ['devices.diagnostics.view'], 'revoke' => []],
            ['email' => 'support1@salamheyetimiz.com', 'name' => 'Support #1', 'role' => 'support', 'password' => 'Supp1!Salam-RBAC#2026', 'grant' => ['devices.diagnostics.view'], 'revoke' => []],
        ];

        $recoveryHashes = array_map(static fn (string $c): string => Hash::make($c), AdminUserSeeder::DEMO_RECOVERY_CODES);
        $idByKey = Permission::query()->pluck('id', 'key')->all();

        foreach ($accounts as $a) {
            /** @var AdminUser $admin */
            $admin = AdminUser::query()->updateOrCreate(
                ['email' => $a['email']],
                [
                    'name' => $a['name'], 'password' => $a['password'], 'role' => $a['role'],
                    'complex_id' => null, 'status' => 'active', 'preferred_language' => 'az',
                    'is_2fa_enabled' => true, 'totp_secret' => AdminUserSeeder::DEMO_TOTP_SECRET,
                    'recovery_codes_hashes' => $recoveryHashes, 'recovery_codes_generated_at' => now(), 'password_changed_at' => now(),
                ],
            );

            // reset prior overrides for a clean, repeatable demo
            UserPermission::query()->where('admin_user_id', $admin->id)->delete();
            foreach (['grant' => $a['grant'], 'revoke' => $a['revoke']] as $effect => $keys) {
                foreach ($keys as $key) {
                    if (! isset($idByKey[$key])) {
                        continue;
                    }
                    UserPermission::query()->create([
                        'admin_user_id' => $admin->id,
                        'permission_id' => (int) $idByKey[$key],
                        'effect' => $effect,
                    ]);
                }
            }
        }
    }
}
