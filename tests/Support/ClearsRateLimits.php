<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Limiter counters live in a cache pool that outlives a single test, and every functional test
 * arrives from 127.0.0.1 — without this the suite throttles itself instead of testing the app.
 *
 * @phpstan-require-extends KernelTestCase
 */
trait ClearsRateLimits
{
    protected static function clearRateLimits(): void
    {
        self::getContainer()->get('cache.rate_limiter')->clear();
    }
}
