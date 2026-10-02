<?php

namespace App\Domain\Applications\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Applications\Exceptions\ApplicationException;
use App\Domain\Applications\Jobs\SendApplicationRejectedEmailJob;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Audit\Services\AuditLogger;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Admin review of a legal-entity application (IMPLEMENTATION_PLAN §11 / B9).
 *
 * approve (ApproveLegalEntityApplication): ONE transaction, row-locked — the complex is created from the
 * application (name, address, OSM lat/lng, region, complexes.legal_entity_application_id), the application
 * becomes approved with complex_id. No Komendant, device or membership is created: the admin assigns the
 * Komendant (B5 link) and binds devices separately.
 *
 * reject (RejectApplication): pending → rejected with a reason; the applicant is emailed (queued, after commit).
 */
final class LegalEntityApplicationReview
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    public function approve(LegalEntityApplication $application, AdminUser $admin, ?int $regionId = null): Complex
    {
        return DB::transaction(function () use ($application, $admin, $regionId): Complex {
            /** @var LegalEntityApplication $locked */
            $locked = LegalEntityApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LegalEntityApplicationStatus::Pending) {
                throw ApplicationException::notPending();
            }

            /** @var Complex $complex */
            $complex = Complex::query()->create([
                'name' => $locked->complex_name,
                'code' => 'LE-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT),
                'region_id' => $regionId ?? $locked->region_id,
                'address' => $locked->address,
                'latitude' => $locked->latitude,
                'longitude' => $locked->longitude,
                'legal_entity_application_id' => $locked->id,
                'is_active' => true,
            ]);

            $locked->forceFill([
                'status' => LegalEntityApplicationStatus::Approved->value,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => $this->clock->now(),
                'complex_id' => $complex->getKey(),
            ])->save();

            $this->audit->record('application.legal_approved', [
                'application_id' => (int) $locked->id,
                'complex_id' => (int) $complex->getKey(),
                'admin_id' => (int) $admin->getKey(),
            ], LegalEntityApplication::class, (int) $locked->id);

            return $complex;
        });
    }

    public function reject(LegalEntityApplication $application, AdminUser $admin, string $reason): LegalEntityApplication
    {
        return DB::transaction(function () use ($application, $admin, $reason): LegalEntityApplication {
            /** @var LegalEntityApplication $locked */
            $locked = LegalEntityApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LegalEntityApplicationStatus::Pending) {
                throw ApplicationException::notPending();
            }

            $locked->forceFill([
                'status' => LegalEntityApplicationStatus::Rejected->value,
                'reviewed_by_admin_id' => $admin->getKey(),
                'reviewed_at' => $this->clock->now(),
                'rejection_reason' => mb_substr(trim($reason), 0, 500),
            ])->save();

            $this->audit->record('application.legal_rejected', [
                'application_id' => (int) $locked->id,
                'admin_id' => (int) $admin->getKey(),
            ], LegalEntityApplication::class, (int) $locked->id);

            SendApplicationRejectedEmailJob::dispatch((int) $locked->id)->afterCommit();

            return $locked;
        });
    }
}
