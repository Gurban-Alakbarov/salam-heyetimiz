<?php

namespace App\Domain\Applications\Support;

use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;

/** One JSON shape per application type, shared by the mobile and admin surfaces (admin adds review fields). */
final class ApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function individual(IndividualApplication $a, bool $admin = false): array
    {
        return [
            'id' => (int) $a->id,
            'type' => 'individual',
            'status' => $a->status->value,
            'full_name' => $a->full_name,
            'phone' => $a->phone,
            'email' => $a->email,
            'address' => $a->address,
            'location' => ['latitude' => (float) $a->latitude, 'longitude' => (float) $a->longitude],
            'region_id' => $a->region_id !== null ? (int) $a->region_id : null,
            'note' => $a->note,
            'rejection_reason' => $a->rejection_reason,
            'editable' => $a->status->value === 'new',
            'created_at' => optional($a->created_at)->toIso8601String(),
            'status_changed_at' => optional($a->status_changed_at)->toIso8601String(),
        ] + ($admin ? [
            'user_id' => (int) $a->user_id,
            'admin_note' => $a->admin_note,
            'handled_by_admin_id' => $a->handled_by_admin_id !== null ? (int) $a->handled_by_admin_id : null,
            'device_id' => $a->device_id !== null ? (int) $a->device_id : null,
            'next_statuses' => array_map(fn ($s) => $s->value, $a->status->next()),
        ] : []);
    }

    /** @return array<string, mixed> */
    public static function legal(LegalEntityApplication $a, bool $admin = false): array
    {
        return [
            'id' => (int) $a->id,
            'type' => 'legal',
            'status' => $a->status->value,
            'complex_name' => $a->complex_name,
            'legal_name' => $a->legal_name,
            'voen' => $a->voen,
            'legal_address' => $a->legal_address,
            'contact_person_name' => $a->contact_person_name,
            'contact_phone' => $a->contact_phone,
            'contact_email' => $a->contact_email,
            'address' => $a->address,
            'location' => ['latitude' => (float) $a->latitude, 'longitude' => (float) $a->longitude],
            'region_id' => $a->region_id !== null ? (int) $a->region_id : null,
            'apartments_count' => $a->apartments_count,
            'note' => $a->note,
            'rejection_reason' => $a->rejection_reason,
            'complex_id' => $a->complex_id !== null ? (int) $a->complex_id : null,
            'editable' => $a->status->value === 'pending',
            'created_at' => optional($a->created_at)->toIso8601String(),
            'reviewed_at' => optional($a->reviewed_at)->toIso8601String(),
        ] + ($admin ? [
            'applicant_user_id' => (int) $a->applicant_user_id,
            'reviewed_by_admin_id' => $a->reviewed_by_admin_id !== null ? (int) $a->reviewed_by_admin_id : null,
        ] : []);
    }
}
