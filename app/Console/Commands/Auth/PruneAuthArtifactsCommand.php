<?php

namespace App\Console\Commands\Auth;

use App\Domain\Auth\Jobs\PruneAuthArtifactsJob;
use Illuminate\Console\Command;

class PruneAuthArtifactsCommand extends Command
{
    protected $signature = 'auth:prune';

    protected $description = 'Prune consumed/expired OTPs, long-revoked refresh tokens, and aged auth attempts.';

    public function handle(): int
    {
        $result = app()->call([new PruneAuthArtifactsJob(), 'handle']);

        $this->info(sprintf(
            'otps=%d refresh_tokens=%d auth_attempts=%d',
            $result['otps'],
            $result['refresh_tokens'],
            $result['auth_attempts'],
        ));

        return self::SUCCESS;
    }
}
