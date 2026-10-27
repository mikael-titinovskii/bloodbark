<?php

namespace App\Dto\Analytics;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ConsentRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i')]
        public string $consentId,
        #[Assert\NotNull]
        public bool $analyticsAllowed,
        #[Assert\NotNull]
        #[Assert\Positive]
        public int $revision,
    ) {
    }
}
