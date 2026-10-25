<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Error pages are rendered by TwigBundle from templates/bundles/TwigBundle/Exception/ instead of
// the controller, and only when the kernel is not in debug — with debug on, Symfony shows the
// profiler exception page instead, which is what make ee sees. These tests therefore boot a
// debug=false kernel; that is the configuration a production 404 goes through.
final class ErrorPageTest extends WebTestCase
{
    public function testTheNotFoundPageKeepsTheSiteChrome(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('GET', '/en');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Pagina nu a fost găsită');
        self::assertSelectorTextContains('main', 'Nu suntem un serviciu de urgență.');
        self::assertSelectorTextContains('footer', 'Suntem un serviciu de informare');
        self::assertSelectorExists('head script[type="module"][src="/assets/app.js"]');
        self::assertSelectorExists('main a[href="/"]');
    }

    public function testAnyOtherErrorFallsBackToTheSameLayout(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->request('POST', '/');

        self::assertResponseStatusCodeSame(405);
        self::assertSelectorTextContains('h1', 'Cererea nu a putut fi finalizată');
        self::assertSelectorTextContains('footer', 'Suntem un serviciu de informare');
        self::assertSelectorExists('head script[type="module"][src="/assets/app.js"]');
    }
}
