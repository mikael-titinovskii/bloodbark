<?php

namespace App\Tests\Controller;

use App\Repository\AnalyticsConsentRepository;
use App\Repository\AnalyticsEventRepository;
use App\Tests\Support\ClearsRateLimits;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class AnalyticsControllerTest extends WebTestCase
{
    use ClearsRateLimits;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        self::clearRateLimits();
        self::ensureKernelShutdown();
    }

    public function testAnAcceptedChoiceStoresAnEventWithSafeProperties(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);
        $this->setConsentCookie($client, $consentId);

        $this->postEvent($client, $name, ['locale' => 'ro', 'route' => 'app_register', 'value' => 1]);

        self::assertResponseStatusCodeSame(204);
        $event = $this->events()->findOneBy(['name' => $name]);
        self::assertNotNull($event);
        self::assertSame(['locale' => 'ro', 'route' => 'app_register', 'value' => 1], $event->getProperties());
        self::assertSame($consentId, $event->getConsent()->getConsentId());
    }

    public function testNoEventIsStoredWithoutTheConsentCookie(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);

        $this->postEvent($client, $name);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->events()->count(['name' => $name]));
    }

    public function testNoEventIsStoredWithoutAServerSideConsentRecord(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->setConsentCookie($client, $consentId);

        $this->postEvent($client, $name);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->events()->count(['name' => $name]));
    }

    public function testWithdrawalDeletesPreviousEventsAndPreventsNewOnes(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);
        $this->setConsentCookie($client, $consentId);
        $this->postEvent($client, $name);
        self::assertSame(1, $this->events()->count(['name' => $name]));

        $this->choose($client, $consentId, false);
        $this->postEvent($client, $name);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->events()->count(['name' => $name]));
        $consent = $this->consents()->findOneByConsentId($consentId);
        self::assertNotNull($consent);
        self::assertFalse($consent->isAnalyticsAllowed());
    }

    public function testAnOldCookieRevisionCannotStoreEvents(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);
        $this->setConsentCookie($client, $consentId, revision: 99);

        $this->postEvent($client, $name);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->events()->count(['name' => $name]));
    }

    public function testAChoiceForAnotherRevisionIsRejected(): void
    {
        $client = self::createClient();

        $this->choose($client, self::consentId(), revision: 99);

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidAndOversizedPropertiesAreRejected(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);
        $this->setConsentCookie($client, $consentId);

        $this->postEvent($client, $name, ['nested' => ['not' => 'allowed']]);
        self::assertResponseStatusCodeSame(422);

        $this->postEvent($client, $name, ['value' => str_repeat('x', 201)]);
        self::assertResponseStatusCodeSame(422);

        $this->postEvent($client, $name, [0 => 'numeric keys are not allowed']);
        self::assertResponseStatusCodeSame(422);

        $this->postEvent($client, $name, ['path' => '/confirm-email/secret-token']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->events()->count(['name' => $name]));
    }

    public function testAnExpiredServerSideConsentCannotStoreEvents(): void
    {
        $client = self::createClient();
        $consentId = self::consentId();
        $name = self::eventName();
        $this->choose($client, $consentId);
        $this->setConsentCookie($client, $consentId);
        $consent = $this->consents()->findOneByConsentId($consentId);
        self::assertNotNull($consent);
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->getConnection()->update(
            'analytics_consent',
            ['updated_at' => (new \DateTimeImmutable('-183 days'))->format('Y-m-d H:i:sP')],
            ['id' => $consent->getId()],
        );

        $this->postEvent($client, $name);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->events()->count(['name' => $name]));
    }

    public function testAnalyticsConfigUsesRouteNamesForTokenPages(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/confirm-email/'.str_repeat('a', 64));

        self::assertResponseStatusCodeSame(410);
        $config = json_decode($crawler->filter('#analytics-config')->text(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame('app_confirm_email', $config['route']);
        self::assertArrayNotHasKey('path', $config);
    }

    public function testPayloadsAreBoundedBeforeJsonMapping(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/analytics/events',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: str_repeat('x', 8193),
        );

        self::assertResponseStatusCodeSame(413);
    }

    public function testMalformedJsonAlsoConsumesTheEventRateLimit(): void
    {
        $client = self::createClient();

        for ($attempt = 1; $attempt <= 121; ++$attempt) {
            $client->request(
                'POST',
                '/analytics/events',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: '{',
            );
        }

        self::assertResponseStatusCodeSame(429);
    }

    private function choose(KernelBrowser $client, string $consentId, bool $allowed = true, int $revision = 1): void
    {
        $client->request(
            'POST',
            '/analytics/consent',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::json([
                'consentId' => $consentId,
                'analyticsAllowed' => $allowed,
                'revision' => $revision,
            ]),
        );
    }

    /** @param array<array-key, mixed> $properties */
    private function postEvent(KernelBrowser $client, string $name, array $properties = []): void
    {
        $client->request(
            'POST',
            '/analytics/events',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: self::json(['name' => $name, 'properties' => $properties]),
        );
    }

    private function setConsentCookie(KernelBrowser $client, string $consentId, int $revision = 1): void
    {
        $client->getCookieJar()->set(new Cookie('bb_consent', self::json([
            'consentId' => $consentId,
            'categories' => ['necessary', 'analytics'],
            'revision' => $revision,
        ])));
    }

    /** @param array<string, mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function events(): AnalyticsEventRepository
    {
        return self::getContainer()->get(AnalyticsEventRepository::class);
    }

    private function consents(): AnalyticsConsentRepository
    {
        return self::getContainer()->get(AnalyticsConsentRepository::class);
    }

    private static function consentId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private static function eventName(): string
    {
        return 'test_'.bin2hex(random_bytes(8));
    }
}
