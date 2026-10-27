<?php

namespace App\Repository;

use App\Entity\AnalyticsConsent;
use App\Entity\AnalyticsEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AnalyticsEvent> */
final class AnalyticsEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsEvent::class);
    }

    public function countDistinctConsents(): int
    {
        return (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(DISTINCT IDENTITY(event.consent)) FROM App\Entity\AnalyticsEvent event',
        )->getSingleScalarResult();
    }

    /** @return array<string, int> */
    public function countsByNameSince(\DateTimeImmutable $since): array
    {
        /** @var list<array{eventName: string, eventCount: int|string}> $rows */
        $rows = $this->getEntityManager()->createQuery(
            'SELECT event.name AS eventName, COUNT(event.id) AS eventCount
            FROM App\Entity\AnalyticsEvent event
            WHERE event.occurredAt >= :since
            GROUP BY event.name
            ORDER BY eventCount DESC, event.name ASC',
        )->setParameter('since', $since)->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['eventName']] = (int) $row['eventCount'];
        }

        return $counts;
    }

    /** @return list<array{date: string, count: int}> */
    public function dailyCountsSince(\DateTimeImmutable $since): array
    {
        /** @var list<array{date: string, count: int|string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT CAST(occurred_at AS date) AS date, COUNT(id) AS count
            FROM analytics_event
            WHERE occurred_at >= :since
            GROUP BY CAST(occurred_at AS date)
            ORDER BY date ASC',
            ['since' => $since->format('Y-m-d H:i:sP')],
        );

        return array_map(static fn (array $row): array => [
            'date' => $row['date'],
            'count' => (int) $row['count'],
        ], $rows);
    }

    public function deleteForConsent(AnalyticsConsent $consent): void
    {
        $this->getEntityManager()->createQuery(
            'DELETE FROM App\Entity\AnalyticsEvent event WHERE event.consent = :consent',
        )->execute(['consent' => $consent]);
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->getEntityManager()->createQuery(
            'DELETE FROM App\Entity\AnalyticsEvent event WHERE event.occurredAt < :cutoff',
        )->execute(['cutoff' => $cutoff]);
    }
}
