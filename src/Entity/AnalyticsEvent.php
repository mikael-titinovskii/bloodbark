<?php

namespace App\Entity;

use App\Repository\AnalyticsEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyticsEventRepository::class)]
#[ORM\Table(name: 'analytics_event')]
#[ORM\Index(name: 'idx_analytics_event_occurred_at', columns: ['occurred_at'])]
#[ORM\Index(name: 'idx_analytics_event_name_occurred_at', columns: ['name', 'occurred_at'])]
class AnalyticsEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnalyticsConsent::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AnalyticsConsent $consent;

    #[ORM\Column(length: 64)]
    private string $name;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $occurredAt;

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: Types::JSON)]
    private array $properties;

    /** @param array<string, scalar|null> $properties */
    public function __construct(AnalyticsConsent $consent, string $name, array $properties = [])
    {
        $this->consent = $consent;
        $this->name = $name;
        $this->properties = $properties;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsent(): AnalyticsConsent
    {
        return $this->consent;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /** @return array<string, scalar|null> */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
