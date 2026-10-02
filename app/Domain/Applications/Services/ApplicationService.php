<?php

namespace App\Domain\Applications\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Applications\Enums\IndividualApplicationStatus;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Exceptions\ApplicationException;
use App\Domain\Applications\Models\IndividualApplication;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Users\Enums\AccountType;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Physical / legal registration applications (IMPLEMENTATION_PLAN §10–§11 / B9, BR-5..7). Two separate
 * models, tables, status sets and endpoints — never mixed. Submitting never creates a complex or a device;
 * the admin lifecycle does (ApproveLegalEntityApplication; private installation via the existing device flow).
 *
 * users.account_type: NULL (legacy) users may apply and are then typed by their first application; a typed
 * user can only file applications of their own type.
 */
final class ApplicationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /** @param  array<string, mixed>  $data  validated */
    public function submitIndividual(User $user, array $data): IndividualApplication
    {
        $this->assertAccountType($user, AccountType::Physical);
        if (IndividualApplication::query()->where('user_id', $user->getKey())->whereNotNull('is_open')->exists()) {
            throw ApplicationException::alreadyOpen();
        }

        try {
            $application = DB::transaction(function () use ($user, $data): IndividualApplication {
                $this->typeAccount($user, AccountType::Physical);

                return IndividualApplication::query()->create($this->individualFields($data) + [
                    'user_id' => $user->getKey(),
                    'status' => IndividualApplicationStatus::New->value,
                    'status_changed_at' => $this->clock->now(),
                ]);
            });
        } catch (QueryException $e) {
            throw str_contains($e->getMessage(), 'uq_individual_applications_open') ? ApplicationException::alreadyOpen() : $e;
        }

        $this->audit->record('application.individual_submitted', ['application_id' => (int) $application->id, 'user_id' => (int) $user->getKey()], IndividualApplication::class, (int) $application->id);

        return $application;
    }

    /** @param  array<string, mixed>  $data  validated */
    public function submitLegal(User $user, array $data): LegalEntityApplication
    {
        $this->assertAccountType($user, AccountType::Legal);
        $this->assertVoenFree((string) $data['voen']);

        try {
            $application = DB::transaction(function () use ($user, $data): LegalEntityApplication {
                $this->typeAccount($user, AccountType::Legal);

                return LegalEntityApplication::query()->create($this->legalFields($data) + [
                    'applicant_user_id' => $user->getKey(),
                    'status' => LegalEntityApplicationStatus::Pending->value,
                ]);
            });
        } catch (QueryException $e) {
            throw str_contains($e->getMessage(), 'uq_legal_entity_applications_pending_voen') ? ApplicationException::duplicateVoen() : $e;
        }

        $this->audit->record('application.legal_submitted', ['application_id' => (int) $application->id, 'user_id' => (int) $user->getKey(), 'voen' => $application->voen], LegalEntityApplication::class, (int) $application->id);

        return $application;
    }

    /** The applicant may edit only while nobody has started on it (status new). */
    public function updateIndividual(IndividualApplication $application, array $data): IndividualApplication
    {
        if ($application->status !== IndividualApplicationStatus::New) {
            throw ApplicationException::notEditable();
        }
        $application->forceFill($this->individualFields($data))->save();
        $this->audit->record('application.individual_updated', ['application_id' => (int) $application->id], IndividualApplication::class, (int) $application->id);

        return $application;
    }

    /** The applicant may edit only while pending review. */
    public function updateLegal(LegalEntityApplication $application, array $data): LegalEntityApplication
    {
        if ($application->status !== LegalEntityApplicationStatus::Pending) {
            throw ApplicationException::notEditable();
        }
        if ((string) $data['voen'] !== $application->voen) {
            $this->assertVoenFree((string) $data['voen'], (int) $application->id);
        }
        $application->forceFill($this->legalFields($data))->save();
        $this->audit->record('application.legal_updated', ['application_id' => (int) $application->id], LegalEntityApplication::class, (int) $application->id);

        return $application;
    }

    /**
     * Admin moves a physical application along its pipeline. `installed` may record the PRIVATE device that was
     * installed (the device itself is registered / assigned through the existing device flow — nothing here).
     */
    public function changeIndividualStatus(
        IndividualApplication $application,
        IndividualApplicationStatus $to,
        AdminUser $admin,
        ?string $adminNote = null,
        ?string $rejectionReason = null,
        ?int $deviceId = null,
    ): IndividualApplication {
        $from = $application->status;
        if (! in_array($to, $from->next(), true)) {
            throw ApplicationException::invalidTransition($from->value, $to->value);
        }
        if ($deviceId !== null && ! Device::query()->whereKey($deviceId)->where('ownership_mode', DeviceOwnershipMode::Private->value)->exists()) {
            throw ApplicationException::invalidDevice();
        }

        $application->forceFill(array_filter([
            'status' => $to->value,
            'status_changed_at' => $this->clock->now(),
            'handled_by_admin_id' => $admin->getKey(),
            'admin_note' => $adminNote,
            'rejection_reason' => $to === IndividualApplicationStatus::Rejected ? $rejectionReason : null,
            'device_id' => $to === IndividualApplicationStatus::Installed ? $deviceId : null,
        ], fn ($v) => $v !== null))->save();

        $this->audit->record('application.individual_status_changed', [
            'application_id' => (int) $application->id,
            'from' => $from->value,
            'to' => $to->value,
            'admin_id' => (int) $admin->getKey(),
            'device_id' => $deviceId,
        ], IndividualApplication::class, (int) $application->id);

        return $application->refresh();
    }

    private function assertAccountType(User $user, AccountType $wanted): void
    {
        $current = $user->getAttributes()['account_type'] ?? null;
        if ($current !== null && $current !== $wanted->value) {
            throw ApplicationException::accountTypeMismatch();
        }
    }

    /** NULL (legacy) accounts are typed by their first application; typed accounts are never re-typed. */
    private function typeAccount(User $user, AccountType $type): void
    {
        User::query()->whereKey($user->getKey())->whereNull('account_type')->update(['account_type' => $type->value]);
    }

    private function assertVoenFree(string $voen, ?int $exceptId = null): void
    {
        $taken = LegalEntityApplication::query()->where('voen', $voen)
            ->where('status', LegalEntityApplicationStatus::Pending->value)
            ->when($exceptId !== null, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
        if ($taken) {
            throw ApplicationException::duplicateVoen();
        }
    }

    /** @return array<string, mixed> */
    private function individualFields(array $d): array
    {
        return [
            'full_name' => trim((string) $d['full_name']),
            'phone' => (string) $d['phone'],
            'email' => mb_strtolower(trim((string) $d['email'])),
            'address' => trim((string) $d['address']),
            'latitude' => round((float) $d['latitude'], 7),
            'longitude' => round((float) $d['longitude'], 7),
            'region_id' => $d['region_id'] ?? null,
            'note' => $d['note'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function legalFields(array $d): array
    {
        return [
            'complex_name' => trim((string) $d['complex_name']),
            'legal_name' => trim((string) $d['legal_name']),
            'voen' => (string) $d['voen'],
            'legal_address' => trim((string) $d['legal_address']),
            'contact_person_name' => trim((string) $d['contact_person_name']),
            'contact_phone' => (string) $d['contact_phone'],
            'contact_email' => mb_strtolower(trim((string) $d['contact_email'])),
            'address' => trim((string) $d['address']),
            'latitude' => round((float) $d['latitude'], 7),
            'longitude' => round((float) $d['longitude'], 7),
            'region_id' => $d['region_id'] ?? null,
            'apartments_count' => $d['apartments_count'] ?? null,
            'note' => $d['note'] ?? null,
        ];
    }
}
