<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\Devices\Models\Device;
use App\Domain\Payments\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Support\Totp;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\DTOs\CreateVisitorLinkData;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Services\VisitorLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** A fixed, valid base32 TOTP secret for admin 2FA tests. */
const TEST_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

/** A fixed install UUID for mobile auth tests. */
const TEST_INSTALL_UUID = '11111111-1111-4111-8111-111111111111';

/*
| Feature & Integration suites use the full framework TestCase against MariaDB (R-CODE-09).
| Unit tests also boot the app (for config()) but do not touch the database.
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

/*
|--------------------------------------------------------------------------
| Shared factory helpers (no model factories defined yet)
|--------------------------------------------------------------------------
*/

function makeUser(string $phone = '+994501112233'): User
{
    return User::query()->create(['phone' => $phone]);
}

function makeSuperAdmin(string $email = 'super@salamhayetimiz.az'): AdminUser
{
    return AdminUser::query()->create([
        'email' => $email,
        'password' => 'password-12chars',
        'name' => 'Super Admin',
        'role' => 'super_admin',
        'is_2fa_enabled' => true,
    ]);
}

function makeTechnicalAdmin(string $email = 'tech@salamhayetimiz.az'): AdminUser
{
    return AdminUser::query()->create([
        'email' => $email,
        'password' => 'password-12chars',
        'name' => 'Tech Admin',
        'role' => 'technical',
        'is_2fa_enabled' => true,
    ]);
}

/** An active admin with any RBAC role (optionally complex-scoped). */
function makeAdminRole(string $role, ?int $complexId = null, ?string $email = null): AdminUser
{
    return AdminUser::query()->create([
        'email' => $email ?? $role.'-'.uniqid().'@salamhayetimiz.az',
        'password' => 'password-12chars',
        'name' => ucfirst($role).' Admin',
        'role' => $role,
        'complex_id' => $complexId,
        'status' => 'active',
        'is_2fa_enabled' => true,
    ]);
}

/** Seed the RBAC catalog + role grants so the DB-driven permission resolution path is exercised. */
function seedRbac(): void
{
    (new \Database\Seeders\Rbac\PermissionSeeder)->run();
    (new \Database\Seeders\Rbac\RolePermissionSeeder)->run();
}

/** A residential complex row for complex-scoping tests. */
function makeComplex(string $code = 'CX-1', string $name = 'Complex 1'): \App\Domain\Admin\Models\Complex
{
    return \App\Domain\Admin\Models\Complex::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
}

function makeUnassignedDevice(string $serial = 'SER-0001', string $sim = '+994700000001'): Device
{
    $model = DeviceModel::query()->firstOrCreate(
        ['vendor' => 'TestVendor', 'model_code' => 'TM-1'],
        ['whitelist_capacity' => 100, 'default_driver_type' => 'traccar'],
    );

    return Device::query()->create([
        'serial' => $serial,
        'device_model_id' => $model->id,
        'sim_phone' => $sim,
        'status' => 'unassigned',
    ]);
}

/** A fully-paid order with a matching approved charge payment, ready for refund tests. */
function makePaidOrder(User $payer, int $amountMinor = 1200, string $bankOrderId = 'KB-PAID-1'): Order
{
    $order = Order::query()->create([
        'reference' => 'SH-TEST-'.uniqid(),
        'payer_user_id' => $payer->getKey(),
        'purpose' => 'sub_main',
        'amount_minor' => $amountMinor,
        'currency' => 'AZN',
        'status' => OrderStatus::Paid,
        'bank_order_id' => $bankOrderId,
        'paid_at' => now(),
    ]);

    $order->payments()->create([
        'bank_transaction_id' => 'TXN-'.$bankOrderId,
        'type' => PaymentType::Charge,
        'amount_minor' => $amountMinor,
        'currency' => 'AZN',
        'status' => PaymentStatus::Approved,
        'occurred_at' => now(),
    ]);

    return $order;
}

/** Sign a raw webhook body with the BirPay X-Signature scheme (Base64 HMAC-SHA256) using the Settings secret. */
function signBirPay(string $rawBody): string
{
    $secret = (string) app(\App\Domain\Admin\Services\SettingsService::class)->value('payments', 'kapital_webhook_secret');

    return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
}

/** Mint a real mobile access token (fp = install_uuid) for guard/end-to-end auth tests. */
function userAccessToken(User $user, string $installUuid = TEST_INSTALL_UUID): string
{
    return app(JwtService::class)->issueUserAccessToken((int) $user->getKey(), $installUuid)->token;
}

/** Mint a real admin access token (tfa_verified=true) for admin guard tests. */
function adminAccessToken(\App\Domain\Admin\Models\AdminUser $admin): string
{
    return app(JwtService::class)->issueAdminAccessToken((int) $admin->getKey(), $admin->role->value)->token;
}

/** Bearer header array for an access token. */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

/** The currently-valid TOTP code for a secret (test helper around the RFC 6238 impl). */
function currentTotp(string $secret = TEST_TOTP_SECRET): string
{
    return Totp::fromConfig()->at($secret);
}

/**
 * Run the full OTP login flow and return the AuthSuccess body (access_token, refresh_token, user).
 *
 * @return array<string, mixed>
 */
function authenticateViaOtp(string $phone, string $installUuid = TEST_INSTALL_UUID): array
{
    test()->postJson('/v1/auth/otp/request', ['phone' => $phone])->assertStatus(202);
    $code = app(\App\Domain\Auth\Adapters\FakeOtpTransport::class)->lastCodeFor($phone);

    return test()->postJson('/v1/auth/otp/verify', [
        'phone' => $phone,
        'code' => $code,
        'device' => ['install_uuid' => $installUuid, 'platform' => 'ios'],
    ])->assertOk()->json();
}

/**
 * A super admin enrolled in 2FA with a known TOTP secret and recovery-code set. The
 * recovery plaintext is returned by reference for consume tests.
 *
 * @param  array<int, string>  $recoveryPlaintext
 */
function makeAdminWith2fa(string $email = 'admin2fa@salamhayetimiz.az', array $recoveryPlaintext = ['0000000001', '0000000002', '0000000003', '0000000004', '0000000005', '0000000006', '0000000007', '0000000008']): \App\Domain\Admin\Models\AdminUser
{
    $admin = makeSuperAdmin($email);

    $admin->forceFill([
        'totp_secret' => TEST_TOTP_SECRET,
        'is_2fa_enabled' => true,
        'recovery_codes_hashes' => array_map(static fn (string $c): string => Hash::make($c), $recoveryPlaintext),
        'recovery_codes_generated_at' => now(),
    ])->save();

    return $admin->refresh();
}

/** An active (sold) device, ready for roster/subscription tests. */
function makeActiveDevice(string $serial = 'SER-ACTIVE-1', string $sim = '+994700009001'): Device
{
    $device = makeUnassignedDevice($serial, $sim);
    $device->update(['status' => 'active']);

    return $device->refresh();
}

/** A device model row (firstOrCreate), needed as a FK target when registering devices. */
function makeDeviceModel(string $vendor = 'TestVendor', string $code = 'TM-1'): DeviceModel
{
    return DeviceModel::query()->firstOrCreate(
        ['vendor' => $vendor, 'model_code' => $code],
        ['whitelist_capacity' => 100, 'default_driver_type' => 'traccar'],
    );
}

/** An openable device with its owner roster row, returned as [device, owner, deviceUser] (DeviceComm transport tests). */
function openableWithRoster(string $phone, string $serial, string $sim): array
{
    $user = makeUser($phone);
    $device = makeOpenableDevice($user, $serial, $sim);
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $user->id)->firstOrFail();

    return [$device, $user, $du];
}

/** An active device owned by $owner, with the owner's roster row in place. */
function makeOwnedDevice(User $owner, string $serial = 'SER-OWN-1', string $sim = '+994700008001'): Device
{
    $device = makeActiveDevice($serial, $sim);
    $device->update(['owner_user_id' => $owner->getKey()]);
    makeDeviceUser($owner, $device, 'owner');

    return $device->refresh();
}

/** An active, owned device whose owner holds an active subscription — open-permitted (R-DOM-05). */
function makeOpenableDevice(User $owner, string $serial = 'SER-OPEN-1', string $sim = '+994700009501'): Device
{
    $device = makeOwnedDevice($owner, $serial, $sim);
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    makeSubscription($du);

    return $device;
}

/**
 * An open_commands row with sane defaults; override state/driver/latency_ms/requested_at via $attrs.
 *
 * @param  array<string, mixed>  $attrs
 */
function makeOpenCommand(Device $device, User $user, DeviceUser $deviceUser, array $attrs = []): OpenCommand
{
    $now = now();

    return OpenCommand::query()->create(array_merge([
        'partition_key' => (int) $now->format('Ym'),
        'device_id' => $device->getKey(),
        'user_id' => $user->getKey(),
        'device_user_id' => $deviceUser->getKey(),
        'driver' => $device->driver_type->value,
        'state' => 'queued',
        'attempts' => 0,
        'requested_at' => $now,
        'source' => 'mobile',
        'created_at' => $now,
    ], $attrs));
}

/** A roster row (device_user) linking a user to a device with a role. */
function makeDeviceUser(User $user, Device $device, string $role = 'owner', string $status = 'active'): DeviceUser
{
    return DeviceUser::query()->create([
        'device_id' => $device->getKey(),
        'user_id' => $user->getKey(),
        'role' => $role,
        'status' => $status,
    ]);
}

/**
 * A subscription attached to a device_user. Defaults to an active main subscription
 * ending one year out; override via $attrs (status, tier, ends_at, price_minor, …).
 *
 * @param  array<string, mixed>  $attrs
 */
function makeSubscription(DeviceUser $deviceUser, array $attrs = []): Subscription
{
    return Subscription::query()->create(array_merge([
        'device_user_id' => $deviceUser->getKey(),
        'tier' => SubscriptionTier::Main,
        'price_minor' => 1200,
        'currency' => 'AZN',
        'term_days' => 365,
        'starts_at' => now(),
        'ends_at' => now()->addDays(365),
        'status' => SubscriptionStatus::Active,
        'auto_renew' => false,
    ], $attrs));
}

/**
 * Mint a visitor link through the real service and return [VisitorLink, plaintext token]. The token is the
 * only moment the secret exists in the clear (only its hash is stored), so tests capture it here.
 *
 * @return array{0: VisitorLink, 1: string}
 */
function mintVisitorLink(Device $device, User|AdminUser $creator, ?VisitorAccessType $type = null, ?int $durationMinutes = 60, ?string $visitorName = 'Kuryer'): array
{
    $result = app(VisitorLinkService::class)->create($device, $creator, new CreateVisitorLinkData(
        accessType: $type ?? VisitorAccessType::TimeLimited,
        visitorName: $visitorName,
        durationMinutes: $durationMinutes,
    ));

    return [$result['link'], $result['token']];
}
