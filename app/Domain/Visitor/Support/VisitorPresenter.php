<?php

namespace App\Domain\Visitor\Support;

use App\Domain\Visitor\Enums\VisitorUsageResult;
use App\Domain\Visitor\Models\VisitorLink;

/**
 * Builds the anonymous-safe view of a visitor link for the public page/endpoint: friendly barrier +
 * resident names, derived status, current validity. No internal ids, no contact details.
 */
final class VisitorPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function status(VisitorLink $link, ?VisitorUsageResult $unavailableReason): array
    {
        $device = $link->device;
        $valid = $unavailableReason === null;

        return [
            'barrier_name' => $device?->location_label ?: __('visitor.default_barrier'),
            'resident_name' => self::residentName($link),
            'visitor_name' => $link->visitor_name,
            'purpose' => $link->purpose?->value,
            'access_type' => $link->access_type->value,
            'status' => $link->statusLabel(now()),
            'valid' => $valid,
            'reason' => $unavailableReason?->value,
            'expires_at' => optional($link->expires_at)->toIso8601String(),
            // Raw coordinates only — the client (mobile native intent / web) builds its own directions.
            // Exposed only while the link is usable, so a dead link never leaks the barrier location.
            'coordinates' => $valid && $device?->latitude !== null && $device?->longitude !== null
                ? ['lat' => (float) $device->latitude, 'lng' => (float) $device->longitude]
                : null,
        ];
    }

    /**
     * The dead-link view for an unknown/malformed token — same shape as status(), no barrier or resident
     * details to leak (we never reveal whether the token ever existed).
     *
     * @return array<string, mixed>
     */
    public static function notFound(): array
    {
        return [
            'barrier_name' => null,
            'resident_name' => null,
            'visitor_name' => null,
            'purpose' => null,
            'access_type' => null,
            'status' => 'not_found',
            'valid' => false,
            'reason' => VisitorUsageResult::NotFound->value,
            'expires_at' => null,
            'coordinates' => null,
        ];
    }

    private static function residentName(VisitorLink $link): ?string
    {
        // Resident-created → the resident's display name; admin-created → the barrier owner's name.
        $name = $link->created_by_user_id !== null
            ? ($link->relationLoaded('createdByUser') ? $link->createdByUser?->full_name : null)
            : ($link->relationLoaded('device') && $link->device?->relationLoaded('owner') ? $link->device->owner?->full_name : null);

        $name = $name !== null ? trim($name) : null;

        return $name !== '' ? $name : null;
    }
}
