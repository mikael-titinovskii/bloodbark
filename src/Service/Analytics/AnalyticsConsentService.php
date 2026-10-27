<?php

namespace App\Service\Analytics;

use App\Dto\Analytics\ConsentRequest;
use App\Entity\AnalyticsConsent;
use App\Repository\AnalyticsConsentRepository;
use App\Repository\AnalyticsEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final readonly class AnalyticsConsentService
{
    public const string COOKIE_NAME = 'bb_consent';

    private const string UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(
        private AnalyticsConsentRepository $consents,
        private AnalyticsEventRepository $events,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.analytics.consent_revision%')] private int $revision,
        #[Autowire('%app.analytics.retention_days%')] private int $retentionDays,
    ) {
    }

    public function saveChoice(ConsentRequest $choice): void
    {
        if (!$this->isCurrentRevision($choice->revision) || !$this->isValidConsentId($choice->consentId)) {
            throw new \InvalidArgumentException('The consent choice is not valid for the current revision.');
        }

        $this->entityManager->wrapInTransaction(function () use ($choice): void {
            $this->lockConsent($choice->consentId);
            $consent = $this->consents->findOneByConsentId($choice->consentId);

            if (null === $consent) {
                $this->entityManager->persist(new AnalyticsConsent(
                    $choice->consentId,
                    $choice->analyticsAllowed,
                    $choice->revision,
                ));
            } elseif ($consent->isAnalyticsAllowed() !== $choice->analyticsAllowed
                || $consent->getRevision() !== $choice->revision) {
                $consent->updateChoice($choice->analyticsAllowed, $choice->revision);

                if (!$choice->analyticsAllowed) {
                    $this->events->deleteForConsent($consent);
                }
            }

            $this->entityManager->flush();
        });
    }

    public function forget(AnalyticsConsent $consent): void
    {
        $this->entityManager->wrapInTransaction(function () use ($consent): void {
            $this->lockConsent($consent->getConsentId());
            $this->events->deleteForConsent($consent);
            $this->entityManager->remove($consent);
            $this->entityManager->flush();
        });
    }

    /** @param callable(AnalyticsConsent): void $record */
    public function recordIfAccepted(Request $request, callable $record): bool
    {
        $consentId = $this->consentIdFromRequest($request);
        if (null === $consentId) {
            return false;
        }

        return $this->entityManager->wrapInTransaction(function () use ($consentId, $record): bool {
            $this->lockConsent($consentId);
            $consent = $this->consents->findOneByConsentId($consentId);

            $expiresBefore = (new \DateTimeImmutable())->modify(sprintf('-%d days', $this->retentionDays));

            if (null === $consent || !$consent->isAnalyticsAllowed() || !$this->isCurrentRevision($consent->getRevision())
                || $consent->getUpdatedAt() <= $expiresBefore) {
                return false;
            }

            $record($consent);
            $this->entityManager->flush();

            return true;
        });
    }

    private function consentIdFromRequest(Request $request): ?string
    {
        $rawCookie = $request->cookies->get(self::COOKIE_NAME);
        if (!\is_string($rawCookie) || '' === $rawCookie || strlen($rawCookie) > 4096) {
            return null;
        }

        try {
            $cookie = json_decode($rawCookie, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($cookie)) {
            return null;
        }

        $consentId = $cookie['consentId'] ?? null;
        $categories = $cookie['categories'] ?? null;
        $revision = $cookie['revision'] ?? null;

        if (!\is_string($consentId) || !$this->isValidConsentId($consentId)
            || !\is_array($categories) || !\in_array('analytics', $categories, true)
            || !$this->isCurrentRevision($revision)) {
            return null;
        }

        return $consentId;
    }

    private function lockConsent(string $consentId): void
    {
        $this->entityManager->getConnection()->executeQuery(
            'SELECT pg_advisory_xact_lock(hashtext(:consentId))',
            ['consentId' => $consentId],
        )->free();
    }

    public function isCurrentRevision(mixed $revision): bool
    {
        return $this->revision === $revision;
    }

    private function isValidConsentId(string $consentId): bool
    {
        return 1 === preg_match(self::UUID_V4_PATTERN, $consentId);
    }
}
