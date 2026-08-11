<?php

namespace App\Domain\Visitor\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\DTOs\CreateVisitorLinkData;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Exceptions\VisitorLinkLimitException;
use App\Domain\Visitor\Models\VisitorLink;
use App\Domain\Visitor\Support\VisitorToken;
use App\Support\Time\Clock;
use Illuminate\Support\Carbon;

/**
 * Owns the visitor-link lifecycle: creation (with token minting + bound resolution) and revocation. The
 * SINGLE place this logic lives — the mobile resident controller and the admin controller both call
 * here, so there is no duplicated business logic (R-ARCH-05/06). Opening a link is VisitorAccessService.
 */
final class VisitorLinkService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Create a link for a device on behalf of a resident (User) or an admin. Returns the model plus the
     * PLAINTEXT token — the only moment it exists in the clear (only the hash is persisted).
     *
     * @return array{link: VisitorLink, token: string, url: string}
     */
    public function create(Device $device, User|AdminUser $creator, CreateVisitorLinkData $data): array
    {
        $now = $this->clock->now();

        $this->assertWithinActiveLimits($device, $creator, Carbon::instance($now));

        $token = VisitorToken::generate();

        [$expiresAt, $maxUsage] = $this->resolveBounds($data, $now);

        $link = VisitorLink::query()->create([
            'device_id' => $device->getKey(),
            'created_by_user_id' => $creator instanceof User ? $creator->getKey() : null,
            'created_by_admin_id' => $creator instanceof AdminUser ? $creator->getKey() : null,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'visitor_name' => $this->clean($data->visitorName),
            'purpose' => $data->purpose?->value,
            'access_type' => $data->accessType->value,
            'expires_at' => $expiresAt,
            'max_usage' => $maxUsage,
            'usage_count' => 0,
        ]);

        return ['link' => $link, 'token' => $token['plain'], 'url' => $this->url($token['plain'])];
    }

    /**
     * Anti-abuse: cap the number of *active* links per device (all creators) and per resident creator.
     * A cap of <= 0 disables that check. Admin creators are exempt from the per-creator cap (they manage on
     * residents' behalf) but the per-device cap still applies to every creator.
     */
    private function assertWithinActiveLimits(Device $device, User|AdminUser $creator, Carbon $now): void
    {
        $perDevice = (int) config('domain.visitor.max_active_per_device');
        if ($perDevice > 0) {
            $count = VisitorLink::query()->where('device_id', $device->getKey())->active($now)->count();
            if ($count >= $perDevice) {
                throw new VisitorLinkLimitException('device', $perDevice);
            }
        }

        $perCreator = (int) config('domain.visitor.max_active_per_creator');
        if ($perCreator > 0 && $creator instanceof User) {
            $count = VisitorLink::query()->where('created_by_user_id', $creator->getKey())->active($now)->count();
            if ($count >= $perCreator) {
                throw new VisitorLinkLimitException('creator', $perCreator);
            }
        }
    }

    public function revoke(VisitorLink $link, User|AdminUser $actor): VisitorLink
    {
        if ($link->revoked_at !== null) {
            return $link; // idempotent
        }

        $link->forceFill([
            'revoked_at' => $this->clock->now(),
            'revoked_by_user_id' => $actor instanceof User ? $actor->getKey() : null,
            'revoked_by_admin_id' => $actor instanceof AdminUser ? $actor->getKey() : null,
        ])->save();

        return $link;
    }

    /** Public shareable URL for a plaintext token (used at creation time only). */
    public function url(string $plainToken): string
    {
        return config('domain.visitor.base_url').'/'.config('domain.visitor.path').'/'.$plainToken;
    }

    /** @return array{0: ?Carbon, 1: ?int} [expires_at, max_usage] */
    private function resolveBounds(CreateVisitorLinkData $data, \DateTimeInterface $now): array
    {
        $now = Carbon::instance($now);

        if ($data->accessType === VisitorAccessType::OneTime) {
            // One successful open, plus a safety expiry so an unused link cannot linger.
            return [$now->copy()->addMinutes((int) config('domain.visitor.one_time_ttl_minutes')), 1];
        }

        $minutes = $data->durationMinutes ?? (int) config('domain.visitor.default_duration_minutes');
        $minutes = max(
            (int) config('domain.visitor.min_duration_minutes'),
            min($minutes, (int) config('domain.visitor.max_duration_minutes')),
        );

        return [$now->copy()->addMinutes($minutes), null];
    }

    private function clean(?string $name): ?string
    {
        $name = $name !== null ? trim($name) : null;

        return $name === '' ? null : $name;
    }
}
