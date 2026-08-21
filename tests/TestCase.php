<?php

namespace Tests;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\RateLimiter;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Neutralise rate limiters in tests: the array cache persists across the run and the
        // IP-keyed 'public' bucket would otherwise trip 429 between unrelated tests. Limiter
        // behaviour itself is exercised in production via RouteServiceProvider (R-SEC-16).
        foreach (['otp-request', 'otp-verify', 'open', 'mobile', 'admin', 'public'] as $bucket) {
            RateLimiter::for($bucket, static fn () => Limit::none());
        }
    }
}
