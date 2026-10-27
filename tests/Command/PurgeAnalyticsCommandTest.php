<?php

namespace App\Tests\Command;

use App\Entity\AnalyticsConsent;
use App\Entity\AnalyticsEvent;
use App\Repository\AnalyticsConsentRepository;
use App\Repository\AnalyticsEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeAnalyticsCommandTest extends KernelTestCase
{
    public function testItDeletesExpiredDataAndKeepsRecentData(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $oldConsent = new AnalyticsConsent(self::consentId(), true, 1);
        $oldEvent = new AnalyticsEvent($oldConsent, 'expired_event');
        $oldOrphan = new AnalyticsConsent(self::consentId(), false, 1);
        $recentConsent = new AnalyticsConsent(self::consentId(), true, 1);
        $recentEvent = new AnalyticsEvent($recentConsent, 'recent_event');
        $recentOrphan = new AnalyticsConsent(self::consentId(), false, 1);

        foreach ([$oldConsent, $oldEvent, $oldOrphan, $recentConsent, $recentEvent, $recentOrphan] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $oldDate = (new \DateTimeImmutable('-183 days'))->format('Y-m-d H:i:sP');
        $connection = $entityManager->getConnection();
        $connection->update('analytics_consent', ['updated_at' => $oldDate], ['id' => $oldConsent->getId()]);
        $connection->update('analytics_consent', ['updated_at' => $oldDate], ['id' => $oldOrphan->getId()]);
        $connection->update('analytics_event', ['occurred_at' => $oldDate], ['id' => $oldEvent->getId()]);

        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:analytics:purge'));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertMatchesRegularExpression('/Deleted [1-9]\d* analytics events and [1-9]\d* expired consent records\./', $tester->getDisplay());

        $entityManager->clear();
        $consents = self::getContainer()->get(AnalyticsConsentRepository::class);
        $events = self::getContainer()->get(AnalyticsEventRepository::class);
        self::assertNull($consents->find($oldConsent->getId()));
        self::assertNull($consents->find($oldOrphan->getId()));
        self::assertNull($events->find($oldEvent->getId()));
        self::assertNotNull($consents->find($recentConsent->getId()));
        self::assertNotNull($consents->find($recentOrphan->getId()));
        self::assertNotNull($events->find($recentEvent->getId()));
    }

    private static function consentId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
