<?php
/**
 * One-off provisioning: create / refresh the super-admin account in production (DATA only — no app
 * code changed). Boots the deployed Laravel app and writes one admin_users row. Re-running resets the
 * password + TOTP secret. Usage on server:  php /root/create_super_admin.php [email]
 */

require '/var/www/salam/vendor/autoload.php';
$app = require_once '/var/www/salam/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Support\Totp;

$email = $argv[1] ?? 'admin@salamheyetimiz.com';
$password = substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(18))), 0, 16);
$secret = Totp::fromConfig()->generateSecret(10); // 16 base32 chars (80-bit, std GA) — fits the encrypted column

$admin = AdminUser::updateOrCreate(
    ['email' => $email],
    [
        'name' => 'Super Admin',
        'password' => $password,          // auto-bcrypt via the model's "hashed" cast
        'role' => 'super_admin',
        'status' => 'active',
        'is_2fa_enabled' => true,
        'totp_secret' => $secret,         // auto-encrypted via the model's "encrypted" cast
    ]
);

echo 'ID=' . $admin->id . "\n";
echo 'EMAIL=' . $email . "\n";
echo 'PASSWORD=' . $password . "\n";
echo 'TOTP_SECRET=' . $secret . "\n";
echo 'CURRENT_CODE=' . Totp::fromConfig()->at($secret) . "\n";
echo 'OTPAUTH=otpauth://totp/Salam%20Admin:' . rawurlencode($email)
    . '?secret=' . $secret . '&issuer=Salam&algorithm=SHA1&digits=6&period=30' . "\n";
