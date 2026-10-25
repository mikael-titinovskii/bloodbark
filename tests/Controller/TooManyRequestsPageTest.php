<?php

namespace App\Tests\Controller;

use App\Tests\Support\ClearsRateLimits;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The profiler's exception page takes over in debug, so the 429 template is only exercised with
 * debug off — the same reason ErrorPageTest boots a non-debug kernel.
 */
final class TooManyRequestsPageTest extends WebTestCase
{
    use ClearsRateLimits;

    protected function setUp(): void
    {
        parent::setUp();

        // The cache pool namespace is derived from the container class, so the counters must be
        // cleared through the same kernel the client below boots: the non-debug one.
        self::bootKernel(['debug' => false]);
        self::clearRateLimits();
        self::ensureKernelShutdown();
    }

    public function testTheRefusedRequestGetsItsOwnPageWithTheSiteChrome(): void
    {
        $client = self::createClient(['debug' => false]);

        // A submission with a bad CSRF token is rejected, but it still counts as one hit, which is
        // all this needs: five fill the window, the sixth is refused.
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $client->request('POST', '/register', ['registration' => ['_token' => 'not-a-real-token']]);
            self::assertResponseStatusCodeSame(422);
        }

        $client->request('POST', '/register', ['registration' => ['_token' => 'not-a-real-token']]);

        self::assertResponseStatusCodeSame(429);
        self::assertTrue($client->getResponse()->headers->has('Retry-After'));
        self::assertSelectorTextContains('h1', 'Prea multe cereri');
        // The site chrome, not Symfony's bare error page: scope banner and emergency notice.
        self::assertSelectorTextContains('footer', 'Suntem un serviciu de informare');
        self::assertSelectorTextContains('footer', 'Nu suntem un serviciu de urgență.');
    }
}
