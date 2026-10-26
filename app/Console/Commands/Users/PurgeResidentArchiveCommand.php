<?php

namespace App\Console\Commands\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Users\Models\ResidentArchive;
use App\Support\Time\Clock;
use Illuminate\Console\Command;

/**
 * O10 — delete archived residents whose retention (privacy.resident_archive_years, set as purge_after when they
 * were archived) has run out. Scheduled daily; the audit row keeps only how many rows and which archive ids.
 */
class PurgeResidentArchiveCommand extends Command
{
    protected $signature = 'residents:purge-archive {--dry-run : Count what would be purged, delete nothing}';

    protected $description = 'Delete archived residents past their retention period (O10)';

    public function handle(Clock $clock, AuditLogger $audit): int
    {
        $due = ResidentArchive::query()->where('purge_after', '<=', $clock->now());
        $ids = (clone $due)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($this->option('dry-run') || $ids === []) {
            $this->info(sprintf('%s %d archived resident(s).', $this->option('dry-run') ? 'would purge' : 'purged', count($ids)));

            return self::SUCCESS;
        }

        ResidentArchive::query()->whereKey($ids)->delete();
        $audit->record('resident_archive.purged', ['count' => count($ids), 'archive_ids' => $ids]);

        $this->info(sprintf('purged %d archived resident(s).', count($ids)));

        return self::SUCCESS;
    }
}
