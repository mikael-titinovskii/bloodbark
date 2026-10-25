<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class HomeControllerTest extends WebTestCase
{
    public function testRomanianIsServedWithoutAPrefix(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="ro"]');
        self::assertSelectorTextContains('main', 'Nu suntem un serviciu de urgență.');
        self::assertSelectorTextContains('footer', 'Suntem un serviciu de informare');
    }

    public function testRussianIsServedUnderItsPrefix(): void
    {
        $client = self::createClient();
        $client->request('GET', '/ru');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="ru"]');
        self::assertSelectorTextContains('main', 'Мы не служба экстренной помощи.');
        self::assertSelectorTextContains('footer', 'Мы — информационный сервис');
    }

    public function testTheSwitcherLeadsToTheSamePageInTheOtherLocale(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertSame('Română', $crawler->filter('nav span[aria-current]')->text());
        self::assertSame('/ru', $crawler->filter('nav a[hreflang="ru"]')->attr('href'));

        $crawler = $client->click($crawler->filter('nav a[hreflang="ru"]')->link());

        self::assertResponseIsSuccessful();
        self::assertSame('/ru', $client->getRequest()->getPathInfo());
        self::assertSame('/', $crawler->filter('nav a[hreflang="ro"]')->attr('href'));
    }

    public function testTheHeadAdvertisesBothLocalesToSearchEngines(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/ru');

        $alternates = $crawler->filter('link[rel="alternate"]')->each(
            static fn (Crawler $link): array => [
                $link->attr('hreflang'),
                parse_url((string) $link->attr('href'), \PHP_URL_PATH),
            ],
        );

        self::assertSame([['ro', '/'], ['ru', '/ru']], $alternates);
    }

    public function testAnUnknownLocaleIsNotFound(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheHomePageLoadsTheSharedBundleThenItsOwn(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        $sources = $crawler->filter('head script[type="module"]')->each(
            static fn (Crawler $script): ?string => $script->attr('src'),
        );

        self::assertSame(['/assets/app.js', '/assets/pages/home.js'], $sources);
    }

    public function testTheSharedBundleIsOnEveryLocale(): void
    {
        $client = self::createClient();
        $client->request('GET', '/ru');

        self::assertSelectorExists('head script[type="module"][src="/assets/app.js"]');
    }
}
