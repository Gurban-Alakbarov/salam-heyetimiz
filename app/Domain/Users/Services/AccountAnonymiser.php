<?php

namespace App\Domain\Users\Services;

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;

/**
 * Y7 / HIGH-12 — strip a deleted account of its personal data, immediately. The row stays (orders, periods,
 * open-command history keep their foreign keys) but no longer identifies anyone:
 *  - phone → `del:<id>` (unique, fits users.phone VARCHAR(20), never a valid +994 number), so the real number
 *    is free to register again (Y3);
 *  - email, full name, verification time, last login IP → null (the email is free again too);
 *  - every install: push token + last-seen IP cleared, install revoked.
 * Returns sha256 of the original phone / email — only for the audit trail (support can match a ghost by hash).
 * Idempotent: an already anonymised row is left as it is.
 *
 * @phpstan-type Hashes array{phone_sha256: ?string, email_sha256: ?string}
 */
final class AccountAnonymiser
{
    public const PHONE_PREFIX = 'del:';

    public function __construct(private readonly Clock $clock) {}

    public static function isAnonymised(User $user): bool
    {
        return str_starts_with((string) $user->phone, self::PHONE_PREFIX);
    }

    /** @return Hashes */
    public function anonymise(User $user): array
    {
        if (self::isAnonymised($user)) {
            return ['phone_sha256' => null, 'email_sha256' => null];
        }

        $hashes = [
            'phone_sha256' => $user->phone !== null ? hash('sha256', (string) $user->phone) : null,
            'email_sha256' => $user->email !== null ? hash('sha256', mb_strtolower((string) $user->email)) : null,
        ];

        $user->forceFill([
            'phone' => self::PHONE_PREFIX.$user->getKey(),
            'email' => null,
            'full_name' => null,
            'email_verified_at' => null,
            'last_login_ip' => null,
            'blocked_reason' => null,
        ])->save();

        UserDevice::query()->where('user_id', $user->getKey())->update([
            'push_token' => null,
            'last_seen_ip' => null,
            'revoked_at' => $this->clock->now(),
        ]);

        return $hashes;
    }
}
