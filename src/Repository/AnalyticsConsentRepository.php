<?php

namespace App\Repository;

use App\Entity\AnalyticsConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AnalyticsConsent> */
final class AnalyticsConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsConsent::class);
    }

    public function findOneByConsentId(string $consentId): ?AnalyticsConsent
    {
        return $this->findOneBy(['consentId' => $consentId]);
    }

    /** @return list<string> */
    public function findConsentIdsUpdatedBefore(\DateTimeImmutable $cutoff): array
    {
        /** @var list<string> $consentIds */
        $consentIds = $this->getEntityManager()->createQuery(
            'SELECT consent.consentId FROM App\Entity\AnalyticsConsent consent WHERE consent.updatedAt < :cutoff',
        )->setParameter('cutoff', $cutoff)->getSingleColumnResult();

        return $consentIds;
    }

    public function deleteOrphansUpdatedBefore(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM analytics_consent AS consent
            WHERE consent.updated_at < :cutoff
            AND NOT EXISTS (
                SELECT 1 FROM analytics_event AS event WHERE event.consent_id = consent.id
            )',
            ['cutoff' => $cutoff->format('Y-m-d H:i:sP')],
        );
    }
}
