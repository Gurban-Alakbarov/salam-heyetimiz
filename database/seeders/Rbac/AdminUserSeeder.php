<?php

namespace Database\Seeders\Rbac;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo admin accounts — one per role — so every role can be logged in and inspected immediately
 * (RBAC_TEST_USERS.md). Idempotent (updateOrCreate by email). 2FA is enabled with a SHARED demo TOTP
 * secret so any authenticator app produces a valid code; a fixed recovery-code set is also provisioned.
 *
 * SECURITY: these are demo credentials with documented passwords. Rotate or disable them before real
 * production use — especially the super_admin demo account.
 */
class AdminUserSeeder extends Seeder
{
    /** Shared demo TOTP secret (base32) — add to any authenticator to generate the 6-digit code. */
    public const DEMO_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    /** Fixed demo recovery codes (single-use each) — usable immediately without an authenticator. */
    public const DEMO_RECOVERY_CODES = [
        'SALAM-RBAC-0001', 'SALAM-RBAC-0002', 'SALAM-RBAC-0003', 'SALAM-RBAC-0004',
        'SALAM-RBAC-0005', 'SALAM-RBAC-0006', 'SALAM-RBAC-0007', 'SALAM-RBAC-0008',
    ];

    public function run(): void
    {
        $complexAId = Complex::query()->where('code', 'SALAM-A')->value('id');

        $recoveryHashes = array_map(static fn (string $code): string => Hash::make($code), self::DEMO_RECOVERY_CODES);

        $accounts = [
            ['email' => 'super@salamheyetimiz.com', 'name' => 'Demo Super Admin', 'role' => 'super_admin', 'password' => 'Sup3r!Salam-RBAC#2026', 'complex_id' => null],
            ['email' => 'technical@salamheyetimiz.com', 'name' => 'Demo Technical', 'role' => 'technical', 'password' => 'Tech!Salam-RBAC#2026', 'complex_id' => null],
            ['email' => 'operator@salamheyetimiz.com', 'name' => 'Demo Operator', 'role' => 'operator', 'password' => 'Oper!Salam-RBAC#2026', 'complex_id' => null],
            ['email' => 'finance@salamheyetimiz.com', 'name' => 'Demo Finance', 'role' => 'finance', 'password' => 'Fin!Salam-RBAC#2026x', 'complex_id' => null],
            ['email' => 'support@salamheyetimiz.com', 'name' => 'Demo Support', 'role' => 'support', 'password' => 'Supp!Salam-RBAC#2026', 'complex_id' => null],
            ['email' => 'manager@salamheyetimiz.com', 'name' => 'Demo Complex Manager', 'role' => 'complex_manager', 'password' => 'Mgr!Salam-RBAC#2026x', 'complex_id' => $complexAId],
        ];

        foreach ($accounts as $account) {
            AdminUser::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $account['role'],
                    'complex_id' => $account['complex_id'],
                    'status' => 'active',
                    'preferred_language' => 'az',
                    'is_2fa_enabled' => true,
                    'totp_secret' => self::DEMO_TOTP_SECRET,
                    'recovery_codes_hashes' => $recoveryHashes,
                    'recovery_codes_generated_at' => now(),
                    'password_changed_at' => now(),
                ],
            );
        }
    }
}
