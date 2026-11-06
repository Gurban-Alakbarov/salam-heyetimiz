<?php

namespace App\Domain\Payments\Support;

/**
 * Decides which PaymentGateway implementation serves the app (IMPLEMENTATION_PLAN B1 / BR-16). Pure so the
 * production guard is unit-testable without booting a production environment.
 *
 *  - testing                                         → fake (in-memory test double; tests drive the bank)
 *  - gateway=kapital (default)                       → kapital (the bank's e-commerce API)
 *  - gateway=fake + fake_enabled (+ allow_fake_in_production in production) → fake
 *  - gateway=fake but not permitted                  → disabled (never falls through to the real bank)
 */
final class PaymentGatewayMode
{
    public const KAPITAL = 'kapital';

    public const FAKE = 'fake';

    public const DISABLED = 'disabled';

    /**
     * @param  array{gateway?: mixed, fake_enabled?: mixed, allow_fake_in_production?: mixed}  $config
     */
    public static function decide(string $environment, array $config): string
    {
        if ($environment === 'testing') {
            return self::FAKE;
        }

        if (($config['gateway'] ?? self::KAPITAL) !== self::FAKE) {
            return self::KAPITAL;
        }

        $enabled = (bool) ($config['fake_enabled'] ?? false);
        $productionAllowed = $environment !== 'production' || (bool) ($config['allow_fake_in_production'] ?? false);

        return $enabled && $productionAllowed ? self::FAKE : self::DISABLED;
    }

    public static function current(): string
    {
        return self::decide((string) app()->environment(), (array) config('domain.payments', []));
    }

    public static function fakeActive(): bool
    {
        return self::current() === self::FAKE;
    }
}
