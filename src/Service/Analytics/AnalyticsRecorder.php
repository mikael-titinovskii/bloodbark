<?php

namespace App\Service\Analytics;

use App\Entity\AnalyticsEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AnalyticsRecorder
{
    private const string EVENT_NAME_PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';
    private const array ALLOWED_PROPERTIES = ['locale', 'route', 'value'];

    public function __construct(
        private AnalyticsConsentService $consents,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<array-key, mixed> $properties */
    public function record(string $name, array $properties = []): bool
    {
        $this->validate($name, $properties);

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        return $this->consents->recordIfAccepted($request, function ($consent) use ($name, $properties): void {
            /* @var array<string, scalar|null> $properties */
            $this->entityManager->persist(new AnalyticsEvent($consent, $name, $properties));
        });
    }

    /** @param array<array-key, mixed> $properties */
    private function validate(string $name, array $properties): void
    {
        if (strlen($name) > 64 || 1 !== preg_match(self::EVENT_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException('Invalid analytics event name.');
        }

        if (\count($properties) > 10) {
            throw new \InvalidArgumentException('Too many analytics event properties.');
        }

        foreach ($properties as $key => $value) {
            if (!\is_string($key) || !\in_array($key, self::ALLOWED_PROPERTIES, true)) {
                throw new \InvalidArgumentException('Invalid analytics event property name.');
            }

            if (null !== $value && !\is_scalar($value)) {
                throw new \InvalidArgumentException('Analytics event properties must be scalar values.');
            }

            if (\is_string($value) && strlen($value) > 200) {
                throw new \InvalidArgumentException('Analytics event property values are limited to 200 bytes.');
            }
        }

        try {
            $encoded = json_encode($properties, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Analytics event properties must be JSON encodable.', previous: $exception);
        }

        if (strlen($encoded) > 2048) {
            throw new \InvalidArgumentException('Analytics event properties are limited to 2048 bytes.');
        }
    }
}
