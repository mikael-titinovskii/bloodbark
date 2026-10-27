<?php

namespace App\Service\Analytics;

use App\Repository\AnalyticsConsentRepository;
use App\Repository\AnalyticsEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AnalyticsRetention
{
    public function __construct(
        private AnalyticsConsentRepository $consents,
        private AnalyticsEventRepository $events,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.analytics.retention_days%')] private int $retentionDays,
    ) {
    }

    /** @return array{events: int, consents: int} */
    public function purge(): array
    {
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $this->retentionDays));

        return $this->entityManager->wrapInTransaction(function () use ($cutoff): array {
            $consentIds = $this->consents->findConsentIdsUpdatedBefore($cutoff);
            sort($consentIds);

            foreach ($consentIds as $consentId) {
                $this->entityManager->getConnection()->executeQuery(
                    'SELECT pg_advisory_xact_lock(hashtext(:consentId))',
                    ['consentId' => $consentId],
                )->free();
            }

            $deletedEvents = $this->events->deleteOlderThan($cutoff);
            $deletedConsents = $this->consents->deleteOrphansUpdatedBefore($cutoff);

            return ['events' => $deletedEvents, 'consents' => $deletedConsents];
        });
    }
}
