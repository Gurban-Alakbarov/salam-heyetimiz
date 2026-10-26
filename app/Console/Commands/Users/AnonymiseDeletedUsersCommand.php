<?php

namespace App\Console\Commands\Users;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountAnonymiser;
use Illuminate\Console\Command;

/**
 * Y3 / Y7 backfill — accounts soft-deleted BEFORE deletion anonymised them still hold their phone / email /
 * name; the unique indexes then keep that phone and email from ever registering again (500 on register).
 * Anonymises every such row (idempotent — already anonymised rows are skipped). Run once on deploy.
 */
class AnonymiseDeletedUsersCommand extends Command
{
    protected $signature = 'users:anonymise-deleted {--dry-run : List what would be anonymised, change nothing}';

    protected $description = 'Erase personal data of soft-deleted accounts that still hold it (Y3/Y7 backfill)';

    public function handle(AccountAnonymiser $anonymiser, AuditLogger $audit): int
    {
        $rows = User::onlyTrashed()
            ->where('phone', 'not like', AccountAnonymiser::PHONE_PREFIX.'%')
            ->orderBy('id')
            ->get();

        foreach ($rows as $user) {
            if ($this->option('dry-run')) {
                $this->line("would anonymise user #{$user->id}");

                continue;
            }

            $hashes = $anonymiser->anonymise($user);
            $audit->record('user.anonymised_backfill', ['user_id' => (int) $user->id] + $hashes, User::class, (int) $user->id);
        }

        $this->info(sprintf('%s %d deleted account(s).', $this->option('dry-run') ? 'would anonymise' : 'anonymised', $rows->count()));

        return self::SUCCESS;
    }
}
