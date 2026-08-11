<?php

namespace App\Console\Commands\Auth;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Generates the RS256 signing keypairs for the mobile and admin JWT audiences
 * (R-SEC-02/08). Dev/test convenience: production injects PEM material via env
 * (AUTH_JWT_*_PRIVATE_KEY / _PUBLIC_KEY) from the secret store instead.
 */
class GenerateAuthKeysCommand extends Command
{
    protected $signature = 'auth:generate-keys {--force : Overwrite existing key files}';

    protected $description = 'Generate RSA keypairs for mobile and admin JWT signing (RS256).';

    public function handle(): int
    {
        $dir = storage_path('keys');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            $this->error("Could not create key directory: {$dir}");

            return self::FAILURE;
        }

        foreach (['user', 'admin'] as $audience) {
            $privatePath = config("domain.auth.keys.{$audience}.private_path");
            $publicPath = config("domain.auth.keys.{$audience}.public_path");

            if (file_exists($privatePath) && ! $this->option('force')) {
                $this->line("• {$audience}: key exists, skipping (use --force to overwrite).");

                continue;
            }

            [$private, $public] = $this->generatePair();

            file_put_contents($privatePath, $private);
            chmod($privatePath, 0600);
            file_put_contents($publicPath, $public);
            chmod($publicPath, 0644);

            $this->info("✓ {$audience}: wrote {$privatePath} + {$publicPath}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} [privatePem, publicPem]
     */
    private function generatePair(): array
    {
        $args = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Windows/XAMPP often cannot locate openssl.cnf on its own.
        $config = $this->opensslConfigPath();
        if ($config !== null) {
            $args['config'] = $config;
        }

        $resource = openssl_pkey_new($args);

        if ($resource === false) {
            throw new RuntimeException('openssl_pkey_new failed: '.openssl_error_string());
        }

        openssl_pkey_export($resource, $private, null, $config !== null ? ['config' => $config] : []);
        $details = openssl_pkey_get_details($resource);

        return [$private, $details['key']];
    }

    private function opensslConfigPath(): ?string
    {
        $candidates = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\apache\\conf\\openssl.cnf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
