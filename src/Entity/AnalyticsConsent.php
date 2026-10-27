<?php

namespace App\Entity;

use App\Repository\AnalyticsConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyticsConsentRepository::class)]
#[ORM\Table(name: 'analytics_consent')]
#[ORM\UniqueConstraint(name: 'uniq_analytics_consent_consent_id', columns: ['consent_id'])]
class AnalyticsConsent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $consentId;

    #[ORM\Column]
    private bool $analyticsAllowed;

    #[ORM\Column]
    private int $revision;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $consentId, bool $analyticsAllowed, int $revision)
    {
        $this->consentId = $consentId;
        $this->analyticsAllowed = $analyticsAllowed;
        $this->revision = $revision;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsentId(): string
    {
        return $this->consentId;
    }

    public function isAnalyticsAllowed(): bool
    {
        return $this->analyticsAllowed;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateChoice(bool $analyticsAllowed, int $revision): void
    {
        $this->analyticsAllowed = $analyticsAllowed;
        $this->revision = $revision;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
