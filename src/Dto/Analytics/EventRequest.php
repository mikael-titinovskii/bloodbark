<?php

namespace App\Dto\Analytics;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventRequest
{
    /** @param array<array-key, mixed> $properties */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        #[Assert\Regex('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/')]
        public string $name,
        #[Assert\Count(max: 10)]
        #[Assert\All([
            new Assert\AtLeastOneOf([
                new Assert\IsNull(),
                new Assert\Type('scalar'),
            ]),
        ])]
        public array $properties = [],
    ) {
    }
}
